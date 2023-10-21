<?php

declare(strict_types=1);

namespace Magephi\Entity\Environment;

use Magephi\Component\DockerCompose;
use Magephi\Component\Yaml;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Magephi\Helper\Database;
use Magephi\Kernel;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class Manager
{
    private SymfonyStyle $output;

    public function __construct(
        private Emakina $emakina,
        private Filesystem $filesystem,
        private Yaml $yaml,
        private DockerCompose $dockerCompose,
        private Database $database
    ) {
        $this->dockerCompose->setEnvironment($this->getEnvironment());
        $this->database->setEnvironment($this->getEnvironment());
    }

    /**
     * Start the current environment.
     */
    public function start(): bool
    {
        return $this->getEnvironment()->start();
    }

    /**
     * Stop the current environment.
     */
    public function stop(): bool
    {
        return $this->getEnvironment()->stop();
    }

    /**
     * Build containers for the current environment.
     */
    public function build(): bool
    {
        return $this->getEnvironment()->build();
    }

    /**
     * Install the current environment and add it to the configuration file.
     *
     * @param array<mixed> $data
     */
    public function install(array $data = []): bool
    {
        if ($this->getEnvironment()->install($data)) {
            $configFile = $this->getConfigFile();
            $this->yaml->update($configFile, ['environment' => [$this->getEnvironment()->getWorkingDir(true) => ['type' => $this->getEnvironment()->getType()]]]);

            return true;
        }

        return false;
    }

    /**
     * Uninstall the current environment and remove it from the configuration file.
     */
    public function uninstall(): bool
    {
        if ($this->getEnvironment()->uninstall()) {
            $configFile = $this->getConfigFile();
            $this->yaml->remove($configFile, ['environment' => $this->getEnvironment()->getWorkingDir(true)]);

            return true;
        }

        return false;
    }

    /**
     * Open a terminal to the given container for the current environment.
     *
     * @param array<string,string> $arguments
     */
    public function openTerminal(string $container, array $arguments): void
    {
        $this->dockerCompose->openTerminal($container, $arguments);
    }

    /**
     * Get variables to configure Docker-Composer for the current environment.
     *
     * @return string[]
     */
    public function getDockerRequiredVariables(): array
    {
        return $this->getEnvironment()->getDockerRequiredVariables();
    }

    /**
     * Get list of files useful to backup when sharing environment.
     *
     * @return string[]
     */
    public function getBackupFiles(): array
    {
        return $this->getEnvironment()->getBackupFiles();
    }

    /**
     * Get current environment.
     */
    public function getEnvironment(): EnvironmentInterface
    {
        return $this->emakina;
    }

    /**
     * @return Manager
     */
    public function setOutput(SymfonyStyle $output): self
    {
        $this->output = $output;
        $this->database->setOutput($output);
        $this->getEnvironment()->setOutput($this->output);

        return $this;
    }

    /**
     * Import database from a file on the project. The file must be at the root or in a direct subdirectory.
     * TODO: Import database from Magento Cloud CLI if available.
     */
    public function importDatabase(string $filename, string $database = null): bool
    {
        if (!$this->dockerCompose->isContainerUp('mysql')) {
            throw new EnvironmentException('Mysql container is not started');
        }

        if (empty($database)) {
            $database = $this->getEnvironment()->getDatabase();
        }

        if ('' === $database) {
            throw new \InvalidArgumentException('The database is not defined. Ensure a database is defined in the configuration or provide one in the command.');
        }

        try {
            $process = $this->database->import($database, $filename);

            if (!$process->getProcess()->isSuccessful()) {
                throw new ProcessException($process->getProcess()->getErrorOutput());
            }

            $seconds = round($process->getDuration());

            $this->output->success(
                sprintf(
                    'The dump has been imported in %s in %d minutes and %d seconds ',
                    $database,
                    $seconds / 60,
                    $seconds % 60
                )
            );

            $this->updateDatabase($database);
        } catch (\Exception $e) {
            $this->output->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Check whether an environment has been installed for this project
     * TODO Check if environment is installed.
     */
    public function hasEnvironment(): bool
    {
        $environment = $this->getEnvironment();

        switch (\get_class($environment)) {
            case Emakina::class:
                return $environment->hasComposeFile();

            default:
                return false;
        }
    }

    /**
     * Update database url with the environment's server name.
     */
    private function updateDatabase(string $database): void
    {
        if ($this->output->confirm('Do you want to update the urls ?', true)) {
            try {
                $process = $this->database->updateUrls($database);

                $this->output->success('The urls were updated.');
            } catch (\Exception $e) {
                $this->output->error($e->getMessage());

                return;
            }

            if (!$process->getProcess()->isSuccessful()) {
                $this->output->error($process->getProcess()->getErrorOutput());
            }
        }
    }

    /**
     * Get the configuration file, create it if it doesn't exist.
     */
    private function getConfigFile(): string
    {
        $dir = Kernel::getCustomDir();
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $configFile = $dir . '/config.yml';
        if (!$this->filesystem->exists($configFile)) {
            $this->filesystem->touch($configFile);
        }

        return $configFile;
    }
}
