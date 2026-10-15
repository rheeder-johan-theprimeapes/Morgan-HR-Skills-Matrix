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
            $connection = $config->get('database.connections.'.$config->get('database.default'));

            if (! is_array($connection)) {
                throw new RuntimeException('The test database connection is missing.');
            }

            TestDatabaseSafety::assertSafe(
                (string) $app->environment(),
                $app->configurationIsCached(),
                $connection,
                $this->developmentDatabase(),
            );

            if (($connection['driver'] ?? null) === 'mysql' && ! is_file(dirname(__DIR__).'/.env.testing')) {
                throw new RuntimeException('MySQL tests require an explicit private .env.testing file.');
            }
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
