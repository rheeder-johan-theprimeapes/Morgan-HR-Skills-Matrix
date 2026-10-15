<?php

declare(strict_types=1);

namespace Tests\Support;

use Dotenv\Exception\InvalidFileException;
use Dotenv\Parser\Parser;
use RuntimeException;

final class TestDatabaseSafety
{
    public const string MYSQL_DATABASE = 'morgan_hr_skills_test';

    /** @var list<string>|null */
    private static ?array $inheritedDatabaseOverrides = null;

    public static function captureInheritedDatabaseEnvironment(): void
    {
        if (self::$inheritedDatabaseOverrides !== null) {
            return;
        }

        self::$inheritedDatabaseOverrides = [];

        foreach (['DB_DATABASE', 'DB_URL'] as $variable) {
            if (array_key_exists($variable, $_SERVER)
                || array_key_exists($variable, $_ENV)
                || getenv($variable) !== false) {
                self::$inheritedDatabaseOverrides[] = $variable;
            }
        }
    }

    /** @return list<string>|null */
    public static function inheritedDatabaseOverrides(): ?array
    {
        return self::$inheritedDatabaseOverrides;
    }

    public static function developmentDatabaseFromContents(?string $contents): ?string
    {
        if ($contents === null) {
            return null;
        }

        try {
            $entries = (new Parser)->parse($contents);
        } catch (InvalidFileException) {
            throw new RuntimeException('Cannot verify development database isolation: invalid .env file.');
        }

        $environment = [];

        foreach ($entries as $entry) {
            if (in_array($entry->getName(), ['DB_DATABASE', 'DB_URL'], true)) {
                $environment[$entry->getName()] = $entry->getValue()->getOrElse(null)?->getChars();
            }
        }

        $database = $environment['DB_DATABASE'] ?? null;
        $url = $environment['DB_URL'] ?? null;

        if ($url !== null && $url !== '') {
            if (str_contains($url, '${')) {
                return null;
            }

            $components = parse_url($url);

            if ($components === false
                || empty($components['scheme'])
                || empty($components['host'])
                || ! empty($components['query'])) {
                return null;
            }

            $database = $components['path'] ?? '';
            $database = rawurldecode(ltrim($database, '/'));
        }

        if ($database === null || trim($database) === '' || str_contains($database, '${')) {
            return null;
        }

        return $database;
    }

    public static function assertConfigurationIsNotCached(bool $cached): void
    {
        if ($cached) {
            throw new RuntimeException('Tests refuse cached configuration. Run php artisan config:clear before testing.');
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  list<string>|null  $inheritedOverrides
     */
    public static function assertSafe(
        string $environment,
        bool $cached,
        array $connection,
        ?string $developmentDatabase,
        ?array $inheritedOverrides = null,
    ): void {
        self::assertConfigurationIsNotCached($cached);

        if ($environment !== 'testing') {
            throw new RuntimeException('Database tests require APP_ENV=testing.');
        }

        if (($connection['driver'] ?? null) === 'mysql' && $inheritedOverrides !== []) {
            throw new RuntimeException('MySQL tests require a captured environment without inherited DB_DATABASE/DB_URL overrides.');
        }

        foreach (['url', 'read', 'write'] as $override) {
            if (! empty($connection[$override])) {
                throw new RuntimeException('Tests refuse database URL and read/write overrides.');
            }
        }

        if (($connection['driver'] ?? null) === 'sqlite' && ($connection['database'] ?? null) === ':memory:') {
            return;
        }

        if ($developmentDatabase === null || trim($developmentDatabase) === '' || str_contains($developmentDatabase, '${')) {
            throw new RuntimeException('Cannot verify development database isolation. MySQL tests require a literal development DB_DATABASE or DB_URL in .env.');
        }

        if (($connection['driver'] ?? null) !== 'mysql'
            || ($connection['database'] ?? null) !== self::MYSQL_DATABASE
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            || ! empty($connection['unix_socket'])
            || strcasecmp($developmentDatabase, self::MYSQL_DATABASE) === 0) {
            throw new RuntimeException('Tests permit only in-memory SQLite or the separate local morgan_hr_skills_test database.');
        }
    }
}
