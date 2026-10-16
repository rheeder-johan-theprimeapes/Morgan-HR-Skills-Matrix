<?php

declare(strict_types=1);
use App\Actions\SaveDevelopmentAction;
use App\Models\DevelopmentAction;
use App\Models\DevelopmentRevision;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTruncation::class);
function committedDevelopmentFixture(): array
{
    $manager = User::factory()->withEmployee()->create();
    $employee = User::factory()->withEmployee()->create();
    $person = $employee->employee;
    $person->forceFill(['manager_id' => $manager->employee_id])->save();
    $action = app(SaveDevelopmentAction::class)->handle($manager, $person, null, ['mode' => 'plan', 'expected_revision' => 0, 'title' => 'Illustrative coaching', 'description' => 'Fictional development plan', 'starts_on' => today()->toDateString(), 'due_on' => today()->addMonth()->toDateString(), 'origin_kind' => 'manual']);

    return [$manager, $employee, $person, $action];
}

it('rejects competing committed progress beyond an older head snapshot', function (): void {
    [$manager,$employee,$person,$action] = committedDevelopmentFixture();
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    config(['database.connections.development_competitor' => $settings]);
    expect(DB::connection('development_competitor')->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($manager, $employee, $person, $action): void {
        expect(DevelopmentAction::query()->findOrFail($action->id)->revision)->toBe(1);
        DB::setDefaultConnection('development_competitor');
        try {
            app(SaveDevelopmentAction::class)->handle($employee, $person, $action, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'First committed learning progress']);
        } finally {
            DB::setDefaultConnection('mysql');
        }
        expect(fn () => app(SaveDevelopmentAction::class)->handle($manager, $person, $action, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Losing stale progress']))->toThrow(ValidationException::class);
    });
    expect($action->fresh()->revision)->toBe(2)->and(DevelopmentRevision::query()->count())->toBe(2)->and($action->fresh()->currentRevision->notes)->toBe('First committed learning progress');
    DB::purge('development_competitor');
});

it('rejects a reporting line removed beyond the supervisor older snapshot', function (): void {
    [$manager,$employee,$person,$action] = committedDevelopmentFixture();
    $settings = config('database.connections.mysql');
    expect($settings['database'])->toBe('morgan_hr_skills_test');
    $other = DB::build($settings);
    expect($other->selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');
    DB::transaction(function () use ($manager, $person, $action, $other): void {
        expect(Employee::query()->findOrFail($person->id)->manager_id)->toBe($manager->employee_id);
        $other->table('employees')->where('id', $person->id)->update(['manager_id' => null]);
        expect(fn () => app(SaveDevelopmentAction::class)->handle($manager, $person, $action, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'in_progress', 'notes' => 'Denied former supervisor']))->toThrow(HttpException::class);
    });
    expect($action->fresh()->revision)->toBe(1);
});

it('preserves a committed completion file when an after commit callback fails', function (): void {
    Storage::fake('evidence');
    [$manager,$employee,$person,$action] = committedDevelopmentFixture();
    DevelopmentRevision::created(function (): void {
        DB::afterCommit(function (): never {
            throw new RuntimeException('Simulated development callback failure after commit');
        });
    });
    try {
        expect(fn () => app(SaveDevelopmentAction::class)->handle($employee, $person, $action, ['mode' => 'progress', 'expected_revision' => 1, 'state' => 'completed', 'completed_on' => today()->toDateString(), 'notes' => ''], UploadedFile::fake()->createWithContent('completion.pdf', "%PDF-1.4\nFictional committed evidence")))->toThrow(RuntimeException::class, 'Simulated development callback failure');
    } finally {
        DevelopmentRevision::flushEventListeners();
        DevelopmentRevision::clearBootedModels();
    }
    $revision = $action->fresh()->currentRevision;
    expect($revision->state->value)->toBe('completed')->and(Storage::disk('evidence')->exists($revision->storage_path))->toBeTrue();
});
