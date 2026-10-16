<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SaveHistoricalDocument;
use App\Models\HistoricalDocument;
use App\Models\User;
use App\Services\HistoricalDocumentAccess;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use LogicException;

class HistoricalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled') !== true || (! app()->environment('testing') && ! SkillsPreviewSeeder::isSafePreview())) {
            throw new LogicException('Fictional history requires an isolated preview/test database.');
        }
        $hr = User::query()->where('login_id', 'DEMO-HR')->first();
        $employee = User::query()->where('login_id', 'DEMO-EMP')->first();
        $person = $employee->employee ?? null;
        if ($hr === null || $person === null || HistoricalDocument::query()->where('employee_id', $person->id)->exists() || ! app(HistoricalDocumentAccess::class)->canAdminister($hr, $person)) {
            return;
        }
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new LogicException('Could not create fictional source.');
        }
        try {
            fwrite($temporary, "%PDF-1.4\nFictional historical preview fixture; not a real PDR.\n%%EOF");
            $metadata = stream_get_meta_data($temporary);
            $path = $metadata['uri'] ?? null;
            if (! is_string($path)) {
                throw new LogicException('Could not inspect fictional source path.');
            }
            $file = new UploadedFile($path, 'fictional-archive.pdf', 'application/pdf', null, true);
            app(SaveHistoricalDocument::class)->handle($hr, $person, null, ['expected_revision' => 0, 'visibility' => 'hr_only', 'title' => 'Illustrative historical archive', 'document_date' => '2025-04-01', 'source' => 'Fictional local preview archive', 'reason' => 'Initial HR-only fictional fixture'], $file);
        } finally {
            fclose($temporary);
        }
    }
}
