<?php

declare(strict_types=1);

use Tests\Support\TestDatabaseSafety;

require_once dirname(__DIR__).'/vendor/autoload.php';

TestDatabaseSafety::captureInheritedDatabaseEnvironment();
