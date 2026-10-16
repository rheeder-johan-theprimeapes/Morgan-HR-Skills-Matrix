<?php

declare(strict_types=1);

use App\Services\ReadTabularSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

function tabularCsv(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('source.csv', $contents);
}

/** @param array<string,string> $sheets @param array<string,string> $extra */
function tabularXlsx(array $sheets, array $extra = []): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'tabular-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $index = 0;
    foreach ($sheets as $name => $xml) {
        $index++;
        $book .= '<sheet name="'.htmlspecialchars((string) $name, ENT_XML1).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
        $rels .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        $zip->addFromString('xl/worksheets/sheet'.$index.'.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>');
    }
    $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('xl/workbook.xml', $book.'</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>');
    foreach ($extra as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
    try {
        return UploadedFile::fake()->createWithContent('source.xlsx', file_get_contents($path));
    } finally {
        unlink($path);
    }
}

function tabularHeader(): string
{
    return '<row r="1"><c r="A1" t="inlineStr"><is><t>Employee ID</t></is></c><c r="B1" t="inlineStr"><is><t>Name</t></is></c></row>';
}

it('preserves UTF-8 quoted commas doubled quotes newlines empty cells and backslashes', function (): void {
    $source = "\xEF\xBB\xBFEmployee ID,Name,Note\r\n001,\"Jo, García\",\"Said \"\"ready\"\"\\path\"\r\n002,Asha,\"First\nSecond\"\r\n003,Dee,\r\n";
    $table = app(ReadTabularSource::class)->read(tabularCsv($source));
    expect($table['headers'])->toBe(['Employee ID', 'Name', 'Note'])
        ->and($table['rows'])->toBe([['001', 'Jo, García', 'Said "ready"\\path'], ['002', 'Asha', "First\nSecond"], ['003', 'Dee', '']])
        ->and($table['row_numbers'])->toBe([2, 3, 4])
        ->and($table['sheet'])->toBeNull();
});

it('skips empty records without trimming or executing literal cell values', function (): void {
    $table = app(ReadTabularSource::class)->read(tabularCsv("ID,Name\n\n001,\"  kept spaces  \"\n,,\n002,=SUM(A1)\n"));
    expect($table['rows'])->toBe([['001', '  kept spaces  '], ['002', '=SUM(A1)']])
        ->and($table['row_numbers'])->toBe([3, 5]);
});

it('rejects malformed CSV sources clearly', function (string $source): void {
    expect(fn () => app(ReadTabularSource::class)->read(tabularCsv($source)))->toThrow(ValidationException::class);
})->with([
    'empty file' => [''],
    'no data' => ["ID,Name\n"],
    'missing header label' => ["ID,\n001,Asha\n"],
    'duplicate header case' => ["ID,id\n001,Asha\n"],
    'unequal columns' => ["ID,Name\n001,Asha,Extra\n"],
    'unterminated quote' => ["ID,Name\n001,\"Asha\n"],
    'stray quote' => ["ID,Name\n001,As\"ha\n"],
    'text after closing quote' => ["ID,Name\n001,\"Asha\"junk\n"],
    'invalid UTF8' => ["ID,Name\n001,\xFF\n"],
    'null byte' => ["ID,Name\n001,Asha\0\n"],
]);

it('bounds source bytes rows columns and cell length', function (string $limit): void {
    $contents = match ($limit) {
        'bytes' => str_repeat('x', 5 * 1024 * 1024 + 1),
        'rows' => "ID,Name\n".str_repeat("001,Asha\n", 1001),
        'columns' => implode(',', array_map(fn (int $i): string => 'Column'.$i, range(1, 65)))."\n".implode(',', array_fill(0, 65, 'value'))."\n",
        'cell' => "ID,Name\n001,".str_repeat('é', 8001)."\n",
    };
    expect(fn () => app(ReadTabularSource::class)->read(tabularCsv($contents)))->toThrow(ValidationException::class);
})->with(['bytes', 'rows', 'columns', 'cell']);

it('reads shared and rich strings literal numbers booleans and sparse worksheet cells', function (): void {
    $xml = '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="inlineStr"><is><t>Name</t></is></c><c r="C1" t="inlineStr"><is><t>Active</t></is></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2" t="inlineStr"><is><r><t>Jo </t></r><r><t>García</t></r></is></c><c r="C2" t="b"><v>1</v></c></row>'
        .'<row r="4"><c r="A4"><v>123</v></c><c r="C4" t="b"><v>0</v></c></row>';
    $shared = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Employee ID</t></si><si><t>001</t></si></sst>';
    $table = app(ReadTabularSource::class)->read(tabularXlsx(['People' => $xml], ['xl/sharedStrings.xml' => $shared]));
    expect($table['sheet'])->toBe('People')->and($table['headers'])->toBe(['Employee ID', 'Name', 'Active'])
        ->and($table['rows'])->toBe([['001', 'Jo García', '1'], ['123', '', '0']])->and($table['row_numbers'])->toBe([2, 4]);
});

it('requires explicit worksheet choice when the workbook contains several sheets', function (): void {
    $rows = tabularHeader().'<row r="2"><c r="A2" t="inlineStr"><is><t>001</t></is></c><c r="B2" t="inlineStr"><is><t>Asha</t></is></c></row>';
    $file = tabularXlsx(['People' => $rows, 'Other' => $rows]);
    expect(fn () => app(ReadTabularSource::class)->read($file))->toThrow(ValidationException::class);
    expect(app(ReadTabularSource::class)->read($file, 'People')['sheet'])->toBe('People');
    expect(fn () => app(ReadTabularSource::class)->read($file, 'Missing'))->toThrow(ValidationException::class);
});

it('rejects formula error unsupported and invalid worksheet coordinates', function (string $cell): void {
    $file = tabularXlsx(['People' => tabularHeader().'<row r="2">'.$cell.'</row>']);
    expect(fn () => app(ReadTabularSource::class)->read($file))->toThrow(ValidationException::class);
})->with([
    'formula' => ['<c r="A2"><f>SUM(A1)</f><v>1</v></c>'],
    'cached error' => ['<c r="A2" t="e"><v>#N/A</v></c>'],
    'unsupported type' => ['<c r="A2" t="mystery"><v>1</v></c>'],
    'wrong row' => ['<c r="A3" t="str"><v>literal</v></c>'],
    'too many columns' => ['<c r="BM2" t="str"><v>literal</v></c>'],
    'duplicate cell' => ['<c r="A2" t="str"><v>one</v></c><c r="A2" t="str"><v>two</v></c>'],
    'out of order cell' => ['<c r="B2" t="str"><v>two</v></c><c r="A2" t="str"><v>one</v></c>'],
    'bad shared index' => ['<c r="A2" t="s"><v>99999</v></c>'],
]);

it('rejects workbook DTD external relationships macros and excessive archive expansion', function (string $invalid): void {
    $extra = match ($invalid) {
        'dtd' => ['xl/workbook.xml' => '<!DOCTYPE workbook [<!ENTITY secret SYSTEM "file:///not-opened">]><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">&secret;</workbook>'],
        'external' => ['xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" TargetMode="External" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="https://example.invalid/never-fetch.xml"/></Relationships>'],
        'macro' => ['xl/vbaProject.bin' => 'not executed'],
        'xml-part' => ['xl/sharedStrings.xml' => str_repeat('x', 2 * 1024 * 1024 + 1)],
        'expansion' => ['unused-large-part.bin' => str_repeat('x', 20 * 1024 * 1024 + 1)],
    };
    $file = tabularXlsx(['People' => tabularHeader()], $extra);
    expect(fn () => app(ReadTabularSource::class)->read($file))->toThrow(ValidationException::class);
})->with(['dtd', 'external', 'macro', 'xml-part', 'expansion']);

it('rejects unsupported file extensions and files that are not real XLSX packages', function (): void {
    foreach (['source.xls', 'source.html', 'source.xlsx'] as $name) {
        expect(fn () => app(ReadTabularSource::class)->read(UploadedFile::fake()->createWithContent($name, 'not a workbook')))->toThrow(ValidationException::class);
    }
});

it('retains numeric worksheet names as names rather than numeric map keys', function (): void {
    $rows = tabularHeader().'<row r="2"><c r="A2" t="str"><v>001</v></c><c r="B2" t="str"><v>Asha</v></c></row>';
    expect(app(ReadTabularSource::class)->read(tabularXlsx(['2026' => $rows]))['sheet'])->toBe('2026');
});

it('rejects a workbook with the wrong XML root even when nested sheets look valid', function (): void {
    $rows = tabularHeader().'<row r="2"><c r="A2" t="str"><v>001</v></c></row>';
    $book = '<unexpected xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="People" sheetId="1" r:id="rId1"/></sheets></unexpected>';
    expect(fn () => app(ReadTabularSource::class)->read(tabularXlsx(['People' => $rows], ['xl/workbook.xml' => $book])))->toThrow(ValidationException::class);
});

it('permits exactly one thousand CSV data records followed by empty records', function (): void {
    $source = "ID,Name\n".str_repeat("001,Asha\n", 1000)."\n\n";
    expect(app(ReadTabularSource::class)->read(tabularCsv($source))['rows'])->toHaveCount(1000);
});
