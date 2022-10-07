<?php

declare(strict_types=1);

namespace Magephi\Component;

use Magephi\Entity\Environment\EnvironmentInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Mutagen
{
    private OutputInterface $output;

    private EnvironmentInterface $environment;

    public function __construct(private ProcessFactory $processFactory)
    {
        $this->output = new ConsoleOutput();
    }

    /**
     * @param EnvironmentInterface $environment
     *
     * @return Mutagen
     */
    public function setEnvironment(EnvironmentInterface $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * Create Mutagen session for the project.
     */
    public function createSession(): Process
    {
        $command = [
            'mutagen',
            'sync',
            'create',
            '--default-owner-beta=id:1000',
            '--default-group-beta=id:1000',
            '--sync-mode=two-way-resolved',
            '--ignore-vcs',
            '--ignore="pub/static"',
            '--ignore="var/page_cache/**"',
            '--ignore="var/composer_home/**"',
            '--ignore="var/view_preprocessed/**"',
            '--ignore="generated/**"',
            '--symlink-mode=posix-raw',
            "--label=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            getcwd() ?: '',
            "docker://{$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}_synchro/var/www/html/",
        ];

        return $this->processFactory->runProcess($command, 60);
    }

    /**
     * Try to resume the Mutagen session. Obviously, it must have been initialized first.
     */
    public function resumeSession(): Process
    {
        return $this->processFactory->runProcess(
            [
                'mutagen',
                'sync',
                'resume',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );
    }

    /**
     * Check if the mutagen session for the project exists. Return true if it does.
     */
    public function isExistingSession(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'sync',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return false === strpos($process->getProcess()->getOutput(), 'No sessions found');
    }

    /**
     * Check if the file synchronization is done.
     */
    public function isSynced(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'sync',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return false !== stripos($process->getProcess()->getOutput(), 'Watching for changes');
    }

    /**
     * Check if the mutagen session is paused.
     */
    public function isPaused(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'sync',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return false !== stripos($process->getProcess()->getOutput(), '[Paused]');
    }

    /**
     * Display a progress bar until the file synchronization is done.
     */
    public function monitorUntilSynced(): bool
    {
        $process = $this->processFactory->createProcess(
            [
                'mutagen',
                'sync',
                'monitor',
                "--label-selector=name=={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ],
            300
        );
        $progressBar = new ProgressBar($this->output, 100);
        $reProgress = '/(\d+)%/i';
        $process->start();
        $progressBar->start();
        $process->getProcess()->waitUntil(
            function (string $type, string $buffer) use ($reProgress, $progressBar) {
                preg_match($reProgress, $buffer, $progressMatch);
                if (!empty($progressMatch)) {
                    $progressBar->setProgress((int) $progressMatch[1]);
                }

                return 'Watching for changes' === ltrim(rtrim($buffer));
            }
        );
        $progressBar->finish();

        return Process::CODE_TIMEOUT !== $process->getExitCode();
    }
}
