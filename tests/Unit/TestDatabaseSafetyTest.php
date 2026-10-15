<?php

declare(strict_types=1);

use Tests\Support\TestDatabaseSafety;

it('permits the isolated SQLite and local MySQL profiles', function (array $connection): void {
    expect(fn () => TestDatabaseSafety::assertSafe('testing', false, $connection, 'hr-skills'))
        ->not->toThrow(Throwable::class);
})->with([
    'in-memory SQLite' => [['driver' => 'sqlite', 'database' => ':memory:']],
    'local MySQL' => [['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => '127.0.0.1']],
]);

it('refuses unsafe configuration before database setup', function (
    string $environment,
    bool $cached,
    array $connection,
    ?string $developmentDatabase,
): void {
    expect(fn () => TestDatabaseSafety::assertSafe($environment, $cached, $connection, $developmentDatabase))
        ->toThrow(RuntimeException::class);
})->with([
    'non-test environment' => ['local', false, ['driver' => 'sqlite', 'database' => ':memory:'], 'hr-skills'],
    'cached configuration' => ['testing', true, ['driver' => 'sqlite', 'database' => ':memory:'], 'hr-skills'],
    'persistent SQLite' => ['testing', false, ['driver' => 'sqlite', 'database' => 'database.sqlite'], 'hr-skills'],
    'development MySQL' => ['testing', false, ['driver' => 'mysql', 'database' => 'hr-skills', 'host' => '127.0.0.1'], 'hr-skills'],
    'test name also used by development' => ['testing', false, ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => '127.0.0.1'], 'morgan_hr_skills_test'],
    'socket override' => ['testing', false, ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => '127.0.0.1', 'unix_socket' => '/var/run/other.sock'], 'hr-skills'],
    'remote MySQL' => ['testing', false, ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => 'production.example'], 'hr-skills'],
    'connection URL' => ['testing', false, ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'mysql://example/production'], 'hr-skills'],
    'read override' => ['testing', false, ['driver' => 'sqlite', 'database' => ':memory:', 'read' => ['database' => 'production']], 'hr-skills'],
    'write override' => ['testing', false, ['driver' => 'sqlite', 'database' => ':memory:', 'write' => ['database' => 'production']], 'hr-skills'],
    'unsupported engine' => ['testing', false, ['driver' => 'pgsql', 'database' => 'morgan_hr_skills_test'], 'hr-skills'],
    'missing database' => ['testing', false, ['driver' => 'sqlite'], 'hr-skills'],
]);
