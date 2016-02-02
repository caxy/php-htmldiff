<?php
/*
 * This file bootstraps the test environment.
 */
namespace Caxy\Tests;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('UTC');

if (!file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    throw new \Exception('Can\'t find autoload.php. Did you install dependencies via composer?');
}

require __DIR__ . '/../../../vendor/autoload.php';
