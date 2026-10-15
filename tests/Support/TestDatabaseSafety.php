<?php

declare(strict_types=1);

namespace Tests\Support;

use Dotenv\Exception\InvalidFileException;
use Dotenv\Parser\Parser;
use RuntimeException;

final class TestDatabaseSafety
{
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

    /** @param array<string, mixed> $connection */
    public static function assertSafe(
        string $environment,
        bool $cached,
        array $connection,
        ?string $developmentDatabase,
    ): void {
        self::assertConfigurationIsNotCached($cached);

        if ($environment !== 'testing') {
            throw new RuntimeException('Database tests require APP_ENV=testing.');
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
            || ($connection['database'] ?? null) !== 'morgan_hr_skills_test'
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            || ! empty($connection['unix_socket'])
            || $developmentDatabase === 'morgan_hr_skills_test') {
            throw new RuntimeException('Tests permit only in-memory SQLite or the separate local morgan_hr_skills_test database.');
        }
    }
}
