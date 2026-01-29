<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

if (!class_exists(Dotenv::class)) {
    throw new LogicException('Please install the "symfony/dotenv" package to load .env files.');
}

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool)$_SERVER['APP_DEBUG']);
$kernel->boot();

return $kernel->getContainer()->get('doctrine.orm.entity_manager');
