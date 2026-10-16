<?php

declare(strict_types=1);

use App\Services\ReadTabularSource;
use Eris\Generators;
use Illuminate\Http\UploadedFile;

it('round trips generated quoted Unicode CSV cells without changing source values', function (): void {
    $this->limitTo(200)->forAll(Generators::seq(Generators::elements('a', 'Z', ',', '"', '\\', "\r", "\n", 'é', ' ')))->then(function (array $characters): void {
        $value = implode('', $characters);
        $stream = fopen('php://temp', 'w+b');
        try {
            fputcsv($stream, ['ID', 'Note'], ',', '"', '', "\r\n");
            fputcsv($stream, ['source-001', $value], ',', '"', '', "\r\n");
            rewind($stream);
            $file = UploadedFile::fake()->createWithContent('generated.csv', stream_get_contents($stream));
            $table = app(ReadTabularSource::class)->read($file);
            expect($table['rows'])->toBe([['source-001', $value]])->and($table['row_numbers'])->toBe([2]);
        } finally {
            fclose($stream);
        }
    });
})->group('fuzz');
