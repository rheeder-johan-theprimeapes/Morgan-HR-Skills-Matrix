<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('exposes only the validated connection for the active test profile', function (): void {
    $profile = constant('MORGAN_TEST_DB_CONNECTION');
    $expected = $profile === 'sqlite'
        ? ['sqlite', ':memory:']
        : ['mysql', 'morgan_hr_skills_test'];
    $connection = DB::connection();

    expect([$connection->getDriverName(), $connection->getDatabaseName()])->toBe($expected);

    $otherConnection = $profile === 'sqlite' ? 'mysql' : 'sqlite';

    expect(fn () => DB::connection($otherConnection))
        ->toThrow(InvalidArgumentException::class, "Database connection [{$otherConnection}] not configured.");
});
