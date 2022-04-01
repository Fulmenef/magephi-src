<?php

declare(strict_types=1);

namespace Magephi;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

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
}
