<?php

declare(strict_types=1);

namespace Magephi\Command;

use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Magephi\Entity\System;
use Magephi\EventListener\CommandListener;
use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    protected SymfonyStyle $interactive;

    public function __construct(
        protected ProcessFactory $processFactory,
        protected DockerCompose $dockerCompose,
        protected Manager $manager
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): static
    {
        $name = 'default' === $name ? $name : 'magephi:' . $name;

        return parent::setName($name);
    }

    /**
     * Checks a condition, outputs a message, and exits if failed.
     *
     * @param string   $success   the success message
     * @param string   $failure   the failure message
     * @param callable $condition the condition to check
     * @param bool     $exit      whether to exit on failure
     *
     * @throws EnvironmentException
     */
    public function check(string $success, string $failure, callable $condition, bool $exit = true): void
    {
        if ($condition()) {
            $this->interactive->writeln("<fg=green>  [*] {$success}</>");
        } elseif (!$exit) {
            $this->interactive->writeln("<fg=yellow>  [!] {$failure}</>");
        } else {
            throw new EnvironmentException($failure);
        }
    }

    /**
     * Contain system prerequisite for the command. Must always follow the same structure.
     * Must contain the exact name of prerequisites defined in the System class.
     *
     * @return array<string, array<string>>
     *
     * @see CommandListener Listener using the variable to check the prerequisites
     * @see System Class containing known prerequisites.
     */
    public function getPrerequisites(): array
    {
        return ['binary' => ['Docker', 'Docker-Compose'], 'service' => ['Docker']];
    }

    protected function configure(): void
    {
        $this->addOption(
            'no-timeout',
            null,
            InputOption::VALUE_NONE,
            'Specify this option to remove timeout limitations.'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->interactive = new SymfonyStyle($input, $output);
        $this->manager->setOutput($this->interactive);
    }
}
