<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $app->beforeBootstrapping(LoadConfiguration::class, function (Application $app): void {
            TestDatabaseSafety::assertConfigurationIsNotCached($app->configurationIsCached());
        });

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $config = $app->make('config');
            $connectionName = defined('MORGAN_TEST_DB_CONNECTION') ? constant('MORGAN_TEST_DB_CONNECTION') : null;

            if (! in_array($connectionName, ['sqlite', 'mysql'], true)) {
                throw new RuntimeException('Tests require an explicit SQLite or MySQL profile.');
            }

            $connection = $config->get('database.connections.'.$connectionName);

            if (! is_array($connection)) {
                throw new RuntimeException('The test database connection is missing.');
            }

            $connection['database'] = $connectionName === 'sqlite' ? ':memory:' : TestDatabaseSafety::MYSQL_DATABASE;

            if ($connectionName === 'sqlite') {
                $connection['url'] = null;
            }

            TestDatabaseSafety::assertSafe(
                (string) $app->environment(),
                $app->configurationIsCached(),
                $connection,
                $this->developmentDatabase(),
                TestDatabaseSafety::inheritedDatabaseOverrides(),
            );

            if (($connection['driver'] ?? null) === 'mysql' && ! is_file(dirname(__DIR__).'/.env.testing')) {
                throw new RuntimeException('MySQL tests require an explicit private .env.testing file.');
            }

            $config->set('database.default', $connectionName);
            $config->set('database.connections', [$connectionName => $connection]);

            $config->set([
                'app.key' => 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
                'app.maintenance.driver' => 'file',
                'hashing.bcrypt.rounds' => 4,
                'broadcasting.default' => 'null',
                'cache.default' => 'array',
                'mail.default' => 'array',
                'queue.default' => 'sync',
                'session.driver' => 'array',
                'pulse.enabled' => false,
                'telescope.enabled' => false,
                'nightwatch.enabled' => false,
            ]);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private function developmentDatabase(): ?string
    {
        $file = dirname(__DIR__).'/.env';

        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException('Cannot verify development database isolation.');
        }

        return TestDatabaseSafety::developmentDatabaseFromContents($contents);
    }
}
