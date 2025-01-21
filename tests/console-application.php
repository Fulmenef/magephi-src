<?php

use Magephi\Application;
use Magephi\Component\ApplicationVersion;
use Magephi\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

/** @phpstan-ignore-next-line */
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$version = new ApplicationVersion($kernel);

return new Application($kernel, $version);
