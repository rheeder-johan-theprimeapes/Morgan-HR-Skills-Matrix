<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use Database\Seeders\SkillsPreviewSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prepare-skills-preview')]
#[Description('Migrate and seed only the explicitly isolated local fictional skills-preview SQLite file')]
class PrepareSkillsPreview extends Command
{
    public function handle(): int
    {
        if (! SkillsPreviewSeeder::isSafePreview()) {
            $this->error('Preview requires local demo mode and the dedicated storage/app/private/skills-preview.sqlite connection.');

            return self::FAILURE;
        }
        if ($this->call('migrate', ['--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        if (Employee::query()->exists()) {
            $this->info('Existing preview records preserved. No reseeding performed.');

            return self::SUCCESS;
        }
        if ($this->call('db:seed', ['--class' => SkillsPreviewSeeder::class, '--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }
        $this->info('Fictional preview ready: DEMO-EMP, DEMO-MGR, DEMO-HR. Local preview password: local-preview-passphrase');

        return self::SUCCESS;
    }
}
