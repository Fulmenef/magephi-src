<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to stop the environment. The install command must have been executed before.
 */
class StopCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'stop';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Stop environment, equivalent to <fg=yellow>make stop</>')
            ->setHelp(
                'This command allows you to stop your Magento 2 environment. It must have been installed before.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->interactive->section('Stopping environment');

        try {
            $this->manager->stop();
        } catch (EnvironmentException $e) {
            $this->interactive->newLine(2);
            $this->interactive->error(
                [
                    "Environment couldn't be stopped: ",
                    $e->getMessage(),
                ]
            );

            return self::FAILURE;
        }

        $this->interactive->newLine(2);
        $this->interactive->success('Environment stopped.');

        return self::SUCCESS;
    }
}
