<?php

declare(strict_types=1);

use Magephi\EventListener\CommandListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true);

    $services
        ->load('Magephi\\', '../src/*')
        ->exclude([
            '../src/DependencyInjection/',
            '../src/Kernel.php',
            '../src/Application.php',
        ]);

    $services->set(CommandListener::class)
        ->tag('kernel.event_listener', ['event' => 'console.command']);
};
