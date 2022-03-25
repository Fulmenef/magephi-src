<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Exception;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Magephi\Entity\System;
use Magephi\Exception\ComposerException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to install the Magento2 project. It'll check if the prerequisites are filled before installing dependencies
 * and setup the Docker environment.
 */
class InstallCommand extends AbstractEnvironmentCommand
{
    public const DOCKER_LOCAL_ENV = 'docker/local/.env';

    protected string $command = 'install';

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Manager $manager,
        private System $system
    ) {
        parent::__construct($processFactory, $dockerCompose, $manager);
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Install the Magento2 project environment in the current directory.')
            ->setHelp('This command allows you to install the Magento 2 environment of the current project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->checkPrerequisites();

            $this->installDependencies();

            $this->interactive->newLine();

            $this->manager->install();

            $imported = $this->importDatabase();
        } catch (Exception $e) {
            if ('' !== $e->getMessage()) {
                $this->interactive->error($e->getMessage());
            }

            return self::FAILURE;
        }

        $this->interactive->newLine(2);

        $this->interactive->success('Your environment has been successfully installed.');

        $environment = $this->manager->getEnvironment();
        $serverName = $environment->getServerName(true);
        if ($imported && $environment->hasMagentoEnv()) {
            $this->interactive->success(
                "Your project is ready, you can access it on {$serverName}"
            );
        } else {
            if (!$environment->hasMagentoEnv()) {
                $this->interactive->warning(
                    'The file app/etc/env.php is missing. Install Magento or retrieve it from another project.'
                );
            }
            if (!$imported) {
                $this->interactive->warning('No database has been imported, install Magento or import the database.');
            }
            $this->interactive->success(
                "Your project is almost ready, it'll will be available on {$serverName}"
            );
        }

        return self::SUCCESS;
    }

    /**
     * Ensure environment is ready.
     */
    protected function checkPrerequisites(): void
    {
        // Run environment checks.
        $this->interactive->section('Environment check');

        $prerequisites = $this->system->getBinaryPrerequisites();
        foreach ($prerequisites as $component => $info) {
            $this->check(
                $component . ' is installed.',
                $component . ' is missing.',
                function () use ($info) {
                    return $info['status'];
                },
                $info['mandatory']
            );
        }

        $prerequisites = $this->system->getServicesPrerequisites();
        foreach ($prerequisites as $component => $info) {
            $this->check(
                $component . ' is running.',
                $component . ' must be started.',
                function () use ($info) {
                    return $info['status'];
                },
                $info['mandatory']
            );
        }
    }

    protected function installDependencies(): void
    {
        $this->interactive->section('Installing dependencies');

        if (!file_exists('composer.json')) {
            throw new ComposerException("Composer.json doesn't exist. Create the project first.");
        }

        $this->processFactory->runInteractiveProcess(
            [
                'composer',
                'install',
                '--ignore-platform-reqs',
                '--optimize-autoloader',
            ],
            900,
            ['COMPOSER_MEMORY_LIMIT' => '2G']
        );
    }

    /**
     * Import database from a file on the project. The file must be at the root or in a direct subdirectory.
     * TODO: Import database from Magento Cloud CLI if available.
     */
    protected function importDatabase(): bool
    {
        $this->interactive->newLine(2);
        $this->interactive->section('Database');

        if ($this->interactive->confirm('Would you like to import a database ?')) {
            if ($files = glob('{*,*/*}{.sql,.sql.zip,.sql.gz,.sql.gzip}', GLOB_BRACE)) {
                if (\count($files) > 1) {
                    array_unshift($files, 'zero');
                    unset($files[0]);
                    $file = $this->interactive->choice(
                        'Multiple compatible files found, please select the correct one:',
                        $files
                    );
                } else {
                    $file = $files[0];
                    if (!$this->interactive->confirm("{$file} is going to be imported, ok ?")) {
                        $file = null;
                    }
                }
                if (\is_string($file)) {
                    return $this->manager->importDatabase($file);
                }
                $this->interactive->error(sprintf('Filename must be a string, %s given', \gettype($file)));
            } else {
                $this->interactive->text('No compatible file found.');
            }
        }

        $this->interactive->text(
            'If you want to import a database later, you can use the <fg=yellow>import</> command.'
        );

        return false;
    }
}
