<?php

declare(strict_types=1);

namespace Magephi;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const NAME = 'Magephi';

    public const VERSION = '@package_version@';

    public const MODE = '@mode@';

    /**
     * Retrieves the custom directory located in the HOME directory of the current user.
     *
     * @throws InvalidConfigurationException
     */
    public static function getCustomDir(): string
    {
        $home = PHP_OS_FAMILY !== 'Windows' ? getenv('HOME') : $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];

        if (\is_string($home) && '' !== $home) {
            $home = rtrim($home, \DIRECTORY_SEPARATOR);
        } else {
            throw new InvalidConfigurationException('Unable to determine the home directory.');
        }

        return "{$home}/.magephi";
    }

    /**
     * Retrieves the custom directory for the current version.
     */
    public function getVersionDir(): string
    {
        return $this->getCustomDir() . '/' . self::VERSION;
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Checks whether the application is currently run as a PHAR.
     */
    public function isArchiveContext(): bool
    {
        return str_contains($this->getProjectDir(), 'phar://');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigurationException
     */
    public function getCacheDir(): string
    {
        return $this->isArchiveContext() ? $this->getVersionDir() . '/cache' : parent::getCacheDir();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigurationException
     */
    public function getLogDir(): string
    {
        return $this->isArchiveContext() ? $this->getVersionDir() . '/log' : parent::getLogDir();
    }

    /**
     * Return the current version if the application is in prod mode
     * Return a tag followed by -dev if in development mode.
     */
    public static function getVersion(): string
    {
        if ('dev' === self::getMode()) {
            return exec('git -C ' . \dirname(__DIR__) . ' describe --tags') . '-dev';
        }

        return self::VERSION;
    }

    /**
     * Return current mode of the application.
     */
    public static function getMode(): string
    {
        $mode = self::MODE;
        if ('prod' !== $mode) {
            $mode = 'dev';
        }

        return $mode;
    }
}
