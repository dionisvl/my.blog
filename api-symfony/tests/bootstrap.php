<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = 'test';
    $_ENV['APP_ENV'] = 'test';
    putenv('APP_ENV=test');
}

if (!isset($_SERVER['APP_DEBUG'])) {
    $_SERVER['APP_DEBUG'] = '1';
    $_ENV['APP_DEBUG'] = '1';
    putenv('APP_DEBUG=1');
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
