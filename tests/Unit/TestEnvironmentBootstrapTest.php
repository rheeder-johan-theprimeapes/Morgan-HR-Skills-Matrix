<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * @param  array<string, string|false>  $environment
 * @return array<string, mixed>
 */
function probeTestServiceConfiguration(string $profile, array $environment = []): array
{
    $code = <<<'PHP'
require 'vendor/autoload.php';
$emitter = PHPUnit\Event\Facade::emitter();
$xml = (new PHPUnit\TextUI\XmlConfiguration\Loader($emitter))->load($argv[1]);
(new PHPUnit\TextUI\Configuration\PhpHandler($emitter))->handle($xml->php());
require 'tests/bootstrap.php';
$case = new class('configurationProbe') extends Tests\TestCase {};
try {
    $app = $case->createApplication();
    $config = $app->make('config');
    echo json_encode([
        'environment' => $app->environment(),
        'mail' => $config->get('mail.default'),
        'cache' => $config->get('cache.default'),
        'queue' => $config->get('queue.default'),
        'session' => $config->get('session.driver'),
        'maintenance' => $config->get('app.maintenance.driver'),
        'key' => $config->get('app.key'),
        'rounds' => $config->get('hashing.bcrypt.rounds'),
    ], JSON_THROW_ON_ERROR);
} catch (RuntimeException $exception) {
    echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;
    $process = new Process([PHP_BINARY, '-r', $code, $profile], dirname(__DIR__, 2), array_replace([
        'APP_ENV' => 'testing',
        'DB_DATABASE' => false,
        'DB_URL' => false,
        'MAIL_MAILER' => 'smtp',
        'CACHE_STORE' => 'redis',
        'QUEUE_CONNECTION' => 'redis',
        'SESSION_DRIVER' => 'redis',
        'APP_MAINTENANCE_DRIVER' => 'cache',
        'APP_KEY' => 'inherited-probe-value',
        'BCRYPT_ROUNDS' => '17',
    ], $environment));

    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

it('installs safe service defaults despite inherited selectors before providers boot', function (string $profile): void {
    expect(probeTestServiceConfiguration($profile))->toBe([
        'environment' => 'testing',
        'mail' => 'array',
        'cache' => 'array',
        'queue' => 'sync',
        'session' => 'array',
        'maintenance' => 'file',
        'key' => 'base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=',
        'rounds' => 4,
    ]);
})->with(defined('MORGAN_TEST_DB_CONNECTION') && constant('MORGAN_TEST_DB_CONNECTION') === 'mysql'
    ? ['phpunit.xml', 'phpunit.mysql.xml']
    : ['phpunit.xml']);

it('continues to refuse an inherited non-test environment before service setup', function (string $profile): void {
    expect(probeTestServiceConfiguration($profile, ['APP_ENV' => 'production']))->toBe([
        'error' => 'Database tests require APP_ENV=testing.',
    ]);
})->with(['phpunit.xml', 'phpunit.mysql.xml']);
