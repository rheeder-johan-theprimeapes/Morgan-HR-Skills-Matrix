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

it('refuses MySQL when the development database identity is unknown or variable-derived', function (?string $contents): void {
    expect(fn () => TestDatabaseSafety::assertSafe(
        'testing',
        false,
        ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => '127.0.0.1'],
        TestDatabaseSafety::developmentDatabaseFromContents($contents),
    ))->toThrow(RuntimeException::class);
})->with([
    'missing development environment' => [null],
    'missing database identity' => ['APP_NAME=Morgan'],
    'empty database name' => ['DB_DATABASE='],
    'blank quoted database name' => ['DB_DATABASE="   "'],
    'external database name' => ['DB_DATABASE=${DB_NAME}'],
    'nested name with apparently separate prefix' => ['DB_DATABASE=hr_${DB_SUFFIX}'],
    'same-file reference can be externally overridden' => ["DB_NAME=hr-skills\nDB_DATABASE=\${DB_NAME}"],
    'external full URL' => ['DB_URL=${DEVELOPMENT_DATABASE_URL}'],
    'variable URL path' => ['DB_URL=mysql://localhost/${DB_NAME}'],
    'variable URL authority' => ['DB_URL=mysql://${DB_AUTHORITY}/hr-skills'],
    'encoded variable URL path' => ['DB_URL=mysql://localhost/%24%7BDB_NAME%7D'],
    'same-file URL reference' => ["DATABASE_URL=mysql://localhost/hr-skills\nDB_URL=\${DATABASE_URL}"],
    'unknown URL does not fall back to a literal name' => ["DB_DATABASE=hr-skills\nDB_URL=\${DATABASE_URL}"],
    'reserved null URL cannot mask the test database' => ["DB_DATABASE=morgan_hr_skills_test\nDB_URL=null"],
    'URL query can override its database path' => ['DB_URL=mysql://localhost/hr-skills?database=morgan_hr_skills_test'],
    'URL without database path' => ['DB_URL=mysql://localhost'],
    'literal development URL uses test schema' => ['DB_URL=mysql://localhost/morgan_hr_skills_test'],
    'encoded development URL uses test schema' => ['DB_URL=mysql://localhost/%6Dorgan_hr_skills_test'],
]);

it('allows MySQL only with a known separate literal development identity', function (string $contents): void {
    expect(fn () => TestDatabaseSafety::assertSafe(
        'testing',
        false,
        ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => 'localhost'],
        TestDatabaseSafety::developmentDatabaseFromContents($contents),
    ))->not->toThrow(Throwable::class);
})->with([
    'literal database name' => ['DB_DATABASE=hr-skills'],
    'quoted database name and comment' => ['DB_DATABASE="hr-skills" # development only'],
    'literal URL takes precedence' => ["DB_DATABASE=morgan_hr_skills_test\nDB_URL=mysql://localhost/hr-skills"],
    'decoded URL database path' => ['DB_URL=mysql://localhost/%68r-skills#fragment'],
    'empty URL falls back to literal name' => ["DB_DATABASE=hr-skills\nDB_URL="],
]);

it('keeps in-memory SQLite usable without an independent development identity', function (?string $contents): void {
    expect(fn () => TestDatabaseSafety::assertSafe(
        'testing',
        false,
        ['driver' => 'sqlite', 'database' => ':memory:'],
        TestDatabaseSafety::developmentDatabaseFromContents($contents),
    ))->not->toThrow(Throwable::class);
})->with([
    'missing environment file' => [null],
    'unresolved name' => ['DB_DATABASE=${DB_NAME}'],
]);

it('refuses the review reproduction even when the referenced name is supplied externally', function (): void {
    $previous = getenv('F00_C01_DATABASE_NAME');
    putenv('F00_C01_DATABASE_NAME=morgan_hr_skills_test');

    try {
        expect(fn () => TestDatabaseSafety::assertSafe(
            'testing',
            false,
            ['driver' => 'mysql', 'database' => 'morgan_hr_skills_test', 'host' => '127.0.0.1'],
            TestDatabaseSafety::developmentDatabaseFromContents('DB_DATABASE=${F00_C01_DATABASE_NAME}'),
        ))->toThrow(RuntimeException::class, 'Cannot verify development database isolation.');
    } finally {
        putenv($previous === false ? 'F00_C01_DATABASE_NAME' : 'F00_C01_DATABASE_NAME='.$previous);
    }
});

it('refuses malformed environment contents without disclosing them', function (): void {
    expect(fn () => TestDatabaseSafety::developmentDatabaseFromContents('DB_DATABASE=private value'))
        ->toThrow(RuntimeException::class, 'Cannot verify development database isolation: invalid .env file.');
});
