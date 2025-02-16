<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

use Magephi\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('docker:exec')
            ->setAliases(['exec'])
            ->setDescription('Execute a command into a container')
            ->setHelp('This command allows you to execute a command into the specified container')
            ->addArgument('content', InputArgument::REQUIRED, 'Command to execute')
            ->addArgument('container', InputArgument::OPTIONAL, 'Container name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $input->getArgument('content');

        if (!\is_string($command)) {
            throw new \InvalidArgumentException(\sprintf('The type should be a string, %s given', \gettype($command)));
        }

        $container = $input->getArgument('container');
        if (\is_string($container)) {
            $process = $this->dockerCompose->executeContainerCommand($container, $command);
        } else {
            $process = $this->dockerCompose->executeGlobalCommand($command);
        }

        if ($process->getProcess()->isSuccessful()) {
            $this->interactive->writeln($process->getProcess()->getOutput());
        } else {
            $this->interactive->error($process->getProcess()->getErrorOutput());
        }

        return self::SUCCESS;
    }
}
