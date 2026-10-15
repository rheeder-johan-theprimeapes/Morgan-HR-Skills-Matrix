<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

it('uses the isolated MySQL database and enforces the configured unique email', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql');
    expect(DB::selectOne('SELECT DATABASE() AS name')->name)->toBe('morgan_hr_skills_test');

    $employee = User::factory()->create();

    expect(fn () => User::factory()->create(['email' => $employee->email]))
        ->toThrow(UniqueConstraintViolationException::class);

    $this->assertDatabaseCount('users', 1);
});

it('rolls back a failed user write transaction on MySQL', function (): void {
    $user = User::factory()->make();

    expect(function () use ($user): void {
        DB::transaction(function () use ($user): void {
            $user->save();

            throw new RuntimeException('Exercise rollback');
        });
    })->toThrow(RuntimeException::class, 'Exercise rollback');

    $this->assertDatabaseMissing('users', ['email' => $user->email]);
});
