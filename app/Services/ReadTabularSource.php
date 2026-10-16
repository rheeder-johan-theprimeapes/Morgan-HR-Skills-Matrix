<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ReadTabularSource
{
    private const int MAX_BYTES = 5 * 1024 * 1024;

    private const int MAX_PART_BYTES = 2 * 1024 * 1024;

    private const string SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const string DOCUMENT_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string PACKAGE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @return array{sheet:?string,headers:list<string>,rows:list<list<string>>,row_numbers:list<int>} */
    public function read(UploadedFile $file, ?string $sheet = null): array
    {
        $size = $file->getSize();
        if (! $file->isValid() || $size < 1 || $size > self::MAX_BYTES) {
            $this->fail('Use a non-empty source file no larger than 5 MiB.');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'csv') {
            return $this->csv($file);
        }
        if ($extension === 'xlsx') {
            return $this->xlsx($file, $sheet);
        }
        $this->fail('Use a UTF-8 CSV or ordinary XLSX source file.');
    }

    /** @return array{sheet:null,headers:list<string>,rows:list<list<string>>,row_numbers:list<int>} */
    private function csv(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getPathname());
        if (! is_string($contents) || ! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            $this->fail('CSV must contain UTF-8 text without null bytes.');
        }
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }
        $this->assertCsvQuotes($contents);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            $this->fail('The source could not be read. Retry.');
        }
        try {
            fwrite($stream, $contents);
            rewind($stream);
            $rows = [];
            $number = 0;
            while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $number++;
                $rows[$number] = array_map(fn (?string $cell): string => $cell ?? '', $row);
                if (array_filter($rows[$number], fn (string $cell): bool => $cell !== '') === []) {
                    unset($rows[$number]);
                }
                if (count($rows) > 1001) {
                    $this->fail('The source exceeds 1,000 data records.');
                }
            }

            return ['sheet' => null] + $this->table($rows, false);
        } finally {
            fclose($stream);
        }
    }

    private function assertCsvQuotes(string $contents): void
    {
        $state = 'start';
        $length = strlen($contents);
        for ($index = 0; $index < $length; $index++) {
            $character = $contents[$index];
            if ($state === 'quoted') {
                if ($character === '"') {
                    if (($contents[$index + 1] ?? '') === '"') {
                        $index++;
                    } else {
                        $state = 'closed';
                    }
                }

                continue;
            }
            if ($character === "\r" && ($contents[$index + 1] ?? '') !== "\n") {
                $this->fail('Use LF or CRLF CSV record endings.');
            }
            if ($character === ',' || $character === "\r" || $character === "\n") {
                $state = 'start';
            } elseif ($state === 'closed' || ($state === 'plain' && $character === '"')) {
                $this->fail('CSV quoting is malformed. Quote entire fields and double embedded quotation marks.');
            } elseif ($state === 'start' && $character === '"') {
                $state = 'quoted';
            } else {
                $state = 'plain';
            }
        }
        if ($state === 'quoted') {
            $this->fail('CSV contains an unterminated quoted field.');
        }
    }

    /** @return array{sheet:string,headers:list<string>,rows:list<list<string>>,row_numbers:list<int>} */
    private function xlsx(UploadedFile $file, ?string $selected): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getPathname(), ZipArchive::RDONLY) !== true) {
            $this->fail('The XLSX source is not a readable workbook package.');
        }
        try {
            $this->assertArchiveBounds($zip);
            $workbook = $this->xml($this->part($zip, 'xl/workbook.xml'), self::SPREADSHEET_NAMESPACE, 'workbook');
            $relationships = $this->xml($this->part($zip, 'xl/_rels/workbook.xml.rels'), self::PACKAGE_RELATIONSHIPS, 'Relationships');
            $targets = [];
            foreach ($relationships->getElementsByTagNameNS(self::PACKAGE_RELATIONSHIPS, 'Relationship') as $relation) {
                if (! in_array($relation->getAttribute('TargetMode'), ['', 'Internal'], true)) {
                    $this->fail('External workbook relationships are not supported.');
                }
                $id = $relation->getAttribute('Id');
                if ($id === '' || isset($targets[$id])) {
                    $this->fail('Workbook relationship identifiers are invalid.');
                }
                $targets[$id] = ['type' => $relation->getAttribute('Type'), 'target' => $relation->getAttribute('Target')];
            }
            $sheets = [];
            foreach ($workbook->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'sheet') as $sheet) {
                $name = $sheet->getAttribute('name');
                $relation = $targets[$sheet->getAttributeNS(self::DOCUMENT_RELATIONSHIPS, 'id')] ?? null;
                if ($name === '' || mb_strlen($name) > 64 || isset($sheets[$name]) || $relation === null || $relation['type'] !== self::DOCUMENT_RELATIONSHIPS.'/worksheet') {
                    $this->fail('Workbook worksheet names or relationships are invalid.');
                }
                $target = str_starts_with($relation['target'], '/') ? substr($relation['target'], 1) : 'xl/'.$relation['target'];
                if (preg_match('/\Axl\/worksheets\/[A-Za-z0-9_.-]+\.xml\z/', $target) !== 1) {
                    $this->fail('Workbook worksheet paths are unsupported.');
                }
                $sheets[$name] = ['name' => $name, 'path' => $target];
            }
            if ($selected === null) {
                if (count($sheets) !== 1) {
                    $this->fail('Choose an explicit worksheet name for a workbook containing several sheets.');
                }
                $selected = array_values($sheets)[0]['name'];
            }
            if (! isset($sheets[$selected])) {
                $this->fail('The selected worksheet is unavailable.');
            }
            $shared = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $strings = $this->xml($this->part($zip, 'xl/sharedStrings.xml'), self::SPREADSHEET_NAMESPACE, 'sst');
                $xpath = new DOMXPath($strings);
                $xpath->registerNamespace('m', self::SPREADSHEET_NAMESPACE);
                foreach ($strings->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'si') as $item) {
                    $text = '';
                    $nodes = $xpath->query('./m:t | ./m:r/m:t', $item);
                    if ($nodes === false) {
                        $this->fail('Shared worksheet strings could not be read.');
                    }
                    foreach ($nodes as $node) {
                        if (! $node instanceof DOMElement) {
                            $this->fail('A shared string contains an invalid text node.');
                        }
                        $text .= $node->textContent;
                    }
                    $this->assertCell($text);
                    $shared[] = $text;
                    if (count($shared) > 65000) {
                        $this->fail('The workbook has too many shared strings.');
                    }
                }
            }
            $worksheet = $this->xml($this->part($zip, $sheets[$selected]['path']), self::SPREADSHEET_NAMESPACE, 'worksheet');
            $rows = [];
            $previousRow = 0;
            foreach ($worksheet->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'row') as $row) {
                $rowNumber = $row->getAttribute('r');
                if (preg_match('/\A[1-9][0-9]{0,6}\z/', $rowNumber) !== 1 || (int) $rowNumber > 1048576 || (int) $rowNumber <= $previousRow) {
                    $this->fail('Worksheet row coordinates are invalid or out of order.');
                }
                $previousRow = (int) $rowNumber;
                $values = [];
                $previousColumn = -1;
                foreach ($row->childNodes as $cell) {
                    if (! $cell instanceof DOMElement || $cell->localName !== 'c' || $cell->namespaceURI !== self::SPREADSHEET_NAMESPACE) {
                        continue;
                    }
                    if (preg_match('/\A([A-Z]{1,3})([1-9][0-9]{0,6})\z/', $cell->getAttribute('r'), $reference) !== 1 || (int) $reference[2] !== $previousRow) {
                        $this->fail('Worksheet cell coordinates do not match their source row.');
                    }
                    $column = 0;
                    foreach (str_split($reference[1]) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $column--;
                    if ($column >= 64 || $column <= $previousColumn) {
                        $this->fail('Worksheet columns exceed 64 or contain duplicate/out-of-order cells.');
                    }
                    $previousColumn = $column;
                    $values[$column] = $this->cell($cell, $shared);
                }
                if (array_filter($values, fn (string $value): bool => $value !== '') !== []) {
                    $rows[$previousRow] = $values;
                    if (count($rows) > 1001) {
                        $this->fail('The worksheet exceeds 1,000 data records.');
                    }
                }
            }

            return ['sheet' => $selected] + $this->table($rows, true);
        } finally {
            $zip->close();
        }
    }

    private function assertArchiveBounds(ZipArchive $zip): void
    {
        if ($zip->numFiles > 2000) {
            $this->fail('The workbook contains too many package entries.');
        }
        $expanded = 0;
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false || $stat['encryption_method'] !== 0 || isset($names[$stat['name']])) {
                $this->fail('The workbook contains invalid, duplicate or encrypted package entries.');
            }
            $name = $stat['name'];
            $names[$name] = true;
            $expanded += $stat['size'];
            if ($expanded > 20 * 1024 * 1024 || str_contains(strtolower($name), 'vbaproject') || str_contains($name, '\\') || str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
                $this->fail('The workbook contains unsupported package content or excessive expanded data.');
            }
        }
    }

    private function part(ZipArchive $zip, string $path): string
    {
        $stat = $zip->statName($path);
        if ($stat === false || $stat['size'] > self::MAX_PART_BYTES) {
            $this->fail('A required workbook XML part is missing or exceeds 2 MiB.');
        }
        $content = $zip->getFromName($path, self::MAX_PART_BYTES + 1);
        if (! is_string($content) || strlen($content) !== $stat['size']) {
            $this->fail('A workbook XML part could not be read within its declared size.');
        }

        return $content;
    }

    private function xml(string $content, string $namespace, string $root): DOMDocument
    {
        if (! mb_check_encoding($content, 'UTF-8') || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $content) === 1 || str_contains($content, "\0")) {
            $this->fail('Workbook XML must be UTF-8 and cannot contain DTD or entity declarations.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($content, LIBXML_NONET | LIBXML_COMPACT) || $document->documentElement?->namespaceURI !== $namespace || $document->documentElement->localName !== $root) {
                $this->fail('A workbook XML part is malformed or uses an unsupported namespace.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param list<string> $shared */
    private function cell(DOMElement $cell, array $shared): string
    {
        if ($cell->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'f')->length > 0) {
            $this->fail('Formula cells cannot be imported. Provide literal source values.');
        }
        $type = $cell->getAttribute('t');
        $value = $cell->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'v')->item(0)->textContent ?? '';
        if ($type === 'inlineStr') {
            $owner = $cell->ownerDocument;
            if ($owner === null) {
                $this->fail('The worksheet cell is detached from its source document.');
            }
            $xpath = new DOMXPath($owner);
            $xpath->registerNamespace('m', self::SPREADSHEET_NAMESPACE);
            $nodes = $xpath->query('./m:is/m:t | ./m:is/m:r/m:t', $cell);
            if ($nodes === false) {
                $this->fail('An inline worksheet string could not be read.');
            }
            $value = '';
            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    $this->fail('An inline string contains an invalid text node.');
                }
                $value .= $node->textContent;
            }
        } elseif ($type === 's') {
            if (preg_match('/\A(?:0|[1-9][0-9]{0,5})\z/', $value) !== 1 || ! isset($shared[(int) $value])) {
                $this->fail('A worksheet cell references an unavailable shared string.');
            }
            $value = $shared[(int) $value];
        } elseif ($type === 'b') {
            if (! in_array($value, ['0', '1'], true)) {
                $this->fail('A worksheet boolean must have literal value 0 or 1.');
            }
        } elseif (in_array($type, ['', 'n'], true)) {
            if ($value !== '' && preg_match('/\A-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/', $value) !== 1) {
                $this->fail('A numeric worksheet cell is not a literal number.');
            }
        } elseif (! in_array($type, ['str', 'd'], true)) {
            $this->fail('Error and unsupported worksheet cell types cannot be imported.');
        }
        $this->assertCell($value);

        return $value;
    }

    private function assertCell(string $value): void
    {
        if (mb_strlen($value) > 8000) {
            $this->fail('A source cell exceeds 8,000 characters.');
        }
    }

    /**
     * @param  array<int,array<int,string>>  $source
     * @return array{headers:list<string>,rows:list<list<string>>,row_numbers:list<int>}
     */
    private function table(array $source, bool $sparse): array
    {
        $firstKey = array_key_first($source);
        $first = $firstKey === null ? null : $source[$firstKey];
        if ($firstKey !== null) {
            unset($source[$firstKey]);
        }
        if ($first === null || $first === [] || count($first) > 64) {
            $this->fail('The source needs a header row with at most 64 columns.');
        }
        $lastColumn = max(array_keys($first));
        $headers = [];
        for ($column = 0; $column <= $lastColumn; $column++) {
            $label = trim($first[$column] ?? '');
            $this->assertCell($label);
            if ($label === '' || in_array(mb_strtolower($label), array_map(mb_strtolower(...), $headers), true)) {
                $this->fail('Header labels must be non-empty and distinct, ignoring case.');
            }
            $headers[] = $label;
        }
        $rows = [];
        $numbers = [];
        foreach ($source as $number => $cells) {
            if ($cells === []) {
                continue;
            }
            if ((! $sparse && count($cells) !== count($headers)) || max(array_keys($cells)) >= count($headers)) {
                $this->fail('Source record '.$number.' has a different column count from the header.');
            }
            $row = [];
            for ($column = 0; $column < count($headers); $column++) {
                $value = $cells[$column] ?? '';
                $this->assertCell($value);
                $row[] = $value;
            }
            $rows[] = $row;
            $numbers[] = $number;
        }
        if ($rows === []) {
            $this->fail('The source needs at least one non-empty data record.');
        }

        return ['headers' => $headers, 'rows' => $rows, 'row_numbers' => $numbers];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['source_file' => $message]);
    }
}
