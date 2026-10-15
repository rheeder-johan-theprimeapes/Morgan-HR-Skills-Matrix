<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class TestDatabaseSafety
{
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

        if (($connection['driver'] ?? null) !== 'mysql'
            || ($connection['database'] ?? null) !== 'morgan_hr_skills_test'
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost'], true)
            || ! empty($connection['unix_socket'])
            || $developmentDatabase === 'morgan_hr_skills_test') {
            throw new RuntimeException('Tests permit only in-memory SQLite or the separate local morgan_hr_skills_test database.');
        }
    }
}
