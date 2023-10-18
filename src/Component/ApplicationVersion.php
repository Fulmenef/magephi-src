<?php

declare(strict_types=1);

namespace Magephi\Component;

use Symfony\Component\HttpKernel\KernelInterface;

class ApplicationVersion
{
    public const APPLICATION_VERSION = '@package_version@';

    public function __construct(private KernelInterface $kernel) {}

    /**
     * Checks whether the application has been compiled as a PHAR archive.
     */
    public function isProd(): bool
    {
        return $this->kernel->getEnvironment() === 'prod';
    }

    /**
     * Return the current version if the application is in prod mode
     * Return a tag followed by -dev if in development mode.
     */
    public function getVersion(): string
    {
        if (!$this->isProd()) {
            return exec('git -C ' . \dirname(__DIR__) . ' describe --tags') . '-dev';
        }

        return self::APPLICATION_VERSION;
    }
}
