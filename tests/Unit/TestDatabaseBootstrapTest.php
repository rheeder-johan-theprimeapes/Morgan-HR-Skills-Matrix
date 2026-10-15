<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('refuses inherited identities through the actual MySQL profile before database use', function (string $variable, string $value): void {
    $projectRoot = dirname(__DIR__, 2);
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_DATABASE' => false,
        'DB_URL' => false,
        $variable => $value,
    ];
    $process = new Process([
        PHP_BINARY,
        'vendor/bin/pest',
        '--configuration=phpunit.mysql.xml',
        'tests/Feature/ExampleTest.php',
        '--do-not-cache-result',
        '--colors=never',
    ], $projectRoot, $environment);

    $process->run();

    expect($process->isSuccessful())->toBeFalse();
    expect($process->getOutput().$process->getErrorOutput())
        ->toContain('MySQL tests require a captured environment without inherited DB_DATABASE/DB_URL overrides.');
})->with([
    'database override matches test schema' => ['DB_DATABASE', 'morgan_hr_skills_test'],
    'empty URL is still an inherited override' => ['DB_URL', ''],
]);

it('captures inherited identity presence from each PHP environment source', function (string $source): void {
    $code = <<<'PHP'
require 'vendor/autoload.php';
unset($_SERVER['DB_DATABASE'], $_SERVER['DB_URL'], $_ENV['DB_DATABASE'], $_ENV['DB_URL']);
putenv('DB_DATABASE');
putenv('DB_URL');
if ($argv[1] === 'process') {
    putenv('DB_DATABASE=');
} elseif ($argv[1] === 'server') {
    $_SERVER['DB_DATABASE'] = '';
} else {
    $_ENV['DB_DATABASE'] = '';
}
require 'tests/bootstrap.php';
echo json_encode(Tests\Support\TestDatabaseSafety::inheritedDatabaseOverrides(), JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-r', $code, $source], dirname(__DIR__, 2));

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe(['DB_DATABASE']);
})->with(['process', 'server', 'environment']);

it('keeps the original snapshot when later bootstrap code changes environment values', function (): void {
    $code = <<<'PHP'
require 'vendor/autoload.php';
unset($_SERVER['DB_DATABASE'], $_SERVER['DB_URL'], $_ENV['DB_DATABASE'], $_ENV['DB_URL']);
putenv('DB_DATABASE');
putenv('DB_URL');
require 'tests/bootstrap.php';
putenv('DB_DATABASE=morgan_hr_skills_test');
$_ENV['DB_DATABASE'] = 'morgan_hr_skills_test';
$_SERVER['DB_DATABASE'] = 'morgan_hr_skills_test';
Tests\Support\TestDatabaseSafety::captureInheritedDatabaseEnvironment();
echo json_encode(Tests\Support\TestDatabaseSafety::inheritedDatabaseOverrides(), JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-r', $code], dirname(__DIR__, 2));

    $process->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))->toBe([]);
});
