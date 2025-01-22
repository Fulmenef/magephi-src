<?php

declare(strict_types=1);

namespace Magephi\Helper;

use Magephi\Component\DockerCompose;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\EnvironmentInterface;

class Make
{
    private EnvironmentInterface $environment;

    public function __construct(
        private DockerCompose $dockerCompose,
        private ProcessFactory $processFactory,
    ) {}

    public function setEnvironment(EnvironmentInterface $environment): void
    {
        $this->environment = $environment;
        $this->dockerCompose->setEnvironment($environment);
    }

    /**
     * Run the `make start` command with a progress bar.
     */
    public function start(bool $install = false): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'start'],
            $_ENV['SHELL_VERBOSITY'] >= 1 ? 360 : 60,
            static function ($type, string $buffer) {
                return (
                    (false !== stripos($buffer, 'Creating')
                        && false !== stripos($buffer, 'done'))
                    || false !== stripos($buffer, 'Created'))
                    || (
                        (false !== stripos($buffer, 'Starting') && false !== stripos($buffer, 'done'))
                        || false !== stripos($buffer, 'Started')
                    );
            },
            $install ? $this->environment->getContainers() * 2 + $this->environment->getVolumes()
                + 3 : $this->environment->getContainers() + 2
        );
    }

    /**
     * Run the `make build` command with a progress bar.
     */
    public function build(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'build'],
            600,
            static function ($type, string $buffer) {
                return stripos($buffer, 'skipping') || stripos($buffer, 'tagged');
            },
            $this->environment->getContainers()
        );
    }

    /**
     * Run the `make stop` command with a progress bar.
     */
    public function stop(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'stop'],
            60,
            static function ($type, string $buffer) {
                return (false !== stripos($buffer, 'stopping') && false !== stripos($buffer, 'done'))
                    || (false !== stripos($buffer, 'Stopped'));
            },
            $this->environment->getContainers() + 1
        );
    }

    /**
     * Run the `make purge` command with a progress bar.
     */
    public function purge(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'purge'],
            300,
            static function ($type, string $buffer) {
                return
                    (false !== stripos($buffer, 'Stopped') || false !== stripos($buffer, 'Removed'))
                    || (stripos($buffer, 'done') && (
                        false !== stripos($buffer, 'stopping')
                            || false !== stripos($buffer, 'removing')
                    ));
            },
            $this->environment->getContainers() * 2 + $this->environment->getVolumes() + 2
        );
    }
}
