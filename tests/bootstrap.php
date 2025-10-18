<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set timezone for tests
date_default_timezone_set('UTC');

// Optional: Define test constants
define('PEKU_TEST_ROOT', __DIR__);
define('PEKU_FIXTURES', __DIR__ . '/Fixtures');