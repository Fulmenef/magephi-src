<?php

declare(strict_types=1);

namespace Magephi\EventListener;

use Magephi\Command\AbstractCommand;
use Magephi\Entity\Environment\Manager;
use Magephi\Entity\System;
use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class CommandListener
{
    public function __construct(private System $system, private Manager $manager) {}

    /**
     * Check if the prerequisites for the Magephi command are filled.
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): int
    {
        // gets the command to be executed
        $command = $event->getCommand();

        /** @var AbstractCommand $command */
        if ($command instanceof AbstractCommand) {
            if (!\in_array(
                $command->getName(),
                ['default', 'magephi:environment:create', 'magephi:update', 'magephi:environment:install'],
                true
            )) {
                if (!$this->manager->hasEnvironment()) {
                    throw new EnvironmentException(
                        'This command cannot be used here, install the environment with the `install` command or go inside a configured project directory.'
                    );
                }
            }

            $commandPrerequisites = $command->getPrerequisites();
            if (!empty($commandPrerequisites['binary'])) {
                $systemPrerequisites = $this->system->getBinaryPrerequisites();
                $this->checkUndefinedPrerequisites(
                    $commandPrerequisites['binary'],
                    $systemPrerequisites,
                    'binary'
                );
                foreach ($commandPrerequisites['binary'] as $prerequisite) {
                    if (!$systemPrerequisites[$prerequisite]['status']) {
                        throw new EnvironmentException(
                            \sprintf('%s is necessary to use this command, please install it.', $prerequisite)
                        );
                    }
                }
            }

            if (!empty($commandPrerequisites['service'])) {
                $systemPrerequisites = $this->system->getServicesPrerequisites();
                $this->checkUndefinedPrerequisites(
                    $commandPrerequisites['service'],
                    $systemPrerequisites,
                    'service'
                );
                foreach ($commandPrerequisites['service'] as $prerequisite) {
                    if (!$systemPrerequisites[$prerequisite]['status']) {
                        throw new EnvironmentException(
                            \sprintf(
                                '%s is not running, the environment must be started to use this command.',
                                $prerequisite
                            )
                        );
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Check if the prerequisites given for the command correspond to prerequisites defined in the System class.
     * Throw an error if it doesn't.
     *
     * @param array<string>                                                         $command
     * @param array<string, array{mandatory: bool, status: bool, comment: ?string}> $system
     *
     * @throws \ArgumentCountError
     */
    private function checkUndefinedPrerequisites(array $command, array $system, string $type): void
    {
        if (!empty($diff = array_diff($command, array_keys($system)))) {
            throw new \ArgumentCountError(
                \sprintf('Undefined %s prerequisite(s) specified: %s', $type, implode(',', $diff))
            );
        }
    }
}
