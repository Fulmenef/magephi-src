<?php

declare(strict_types=1);

namespace Magephi;

use Magephi\Component\ApplicationVersion;
use Symfony\Bundle\FrameworkBundle\Console\Application as BaseApplication;
use Symfony\Component\HttpKernel\KernelInterface;

class Application extends BaseApplication
{
    public const APPLICATION_NAME = 'Magephi';

    public function __construct(KernelInterface $kernel, private ApplicationVersion $version)
    {
        parent::__construct($kernel);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::APPLICATION_NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return $this->version->getVersion();
    }
}
