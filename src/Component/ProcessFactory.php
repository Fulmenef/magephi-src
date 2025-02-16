<?php

declare(strict_types=1);

namespace Magephi\Component;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;

class ProcessFactory
{
    private OutputInterface $ouputInterface;

    private ArgvInput $inputInterface;

    public function __construct()
    {
        $this->ouputInterface = new ConsoleOutput();
        $this->inputInterface = new ArgvInput();
    }

    /**
     * Create an instance of Process with the given command and return it.
     *
     * @param string[]      $command
     * @param null|string[] $env     Environment variables
     * @param bool          $shell   Specify if the process should execute the command in shell directly
     */
    public function createProcess(
        array $command,
        ?float $timeout = 3600.00,
        ?array $env = null,
        bool $shell = false
    ): Process {
        return new Process(
            $command,
            $this->inputInterface->hasParameterOption('--no-timeout') ? null : $timeout,
            $env,
            $shell
        );
    }

    /**
     * Run a process directly without any customization.
     *
     * @param string[] $command
     * @param string[] $env     Environment variables
     * @param bool     $shell   Should the process be executed as shell command directly
     */
    public function runProcess(
        array $command,
        float $timeout = 3600.00,
        array $env = [],
        bool $shell = false
    ): Process {
        if ($_ENV['SHELL_VERBOSITY'] >= 1) {
            return $this->runProcessWithOutput($command, $timeout, $env, $shell);
        }
        $process = $this->createProcess($command, $timeout, $env, $shell);
        $process->run();

        return $process;
    }

    /**
     * Create and start a process with an associated progress bar.
     *
     * @param string[] $command
     * @param callable $progressFunction Used to update the progress bar. Return true to advance by 1, return an
     *                                   int to advance the bar with the number of steps.
     * @param bool     $shell            Specify if the process should execute the command in shell directly
     */
    public function runProcessWithProgressBar(
        array $command,
        float $timeout,
        callable $progressFunction,
        ?int $maxSteps = null,
        bool $shell = false
    ): Process {
        if ($_ENV['SHELL_VERBOSITY'] >= 1) {
            return $this->runProcessWithOutput($command, $timeout, [], $shell);
        }
        $process = $this->createProcess($command, $timeout, null, $shell);
        $process->createProgressBar($this->ouputInterface, $maxSteps);
        $process->setProgressCallback($progressFunction);
        $process->start();

        return $process;
    }

    /**
     * Run a process with output.
     *
     * @param string[]      $command
     * @param null|string[] $env     Environment variables
     */
    public function runProcessWithOutput(
        array $command,
        ?float $timeout = null,
        ?array $env = null,
        bool $shell = false
    ): Process {
        return $this->runOutputProcess($command, $timeout, $env, $shell);
    }

    /**
     * Run a process and provide an interactive interface.
     *
     * @param string[]      $command
     * @param null|string[] $env     Environment variables
     */
    public function runInteractiveProcess(array $command, ?float $timeout = null, ?array $env = null): Process
    {
        return $this->runOutputProcess($command, $timeout, $env, true, true);
    }

    /**
     * Run a command with its output.
     *
     * @param string[]      $command
     * @param null|string[] $env
     */
    private function runOutputProcess(
        array $command,
        ?float $timeout = null,
        ?array $env = null,
        bool $shell = false,
        bool $tty = false
    ): Process {
        $process = $this->createProcess($command, $timeout, $env, $shell);

        $process->getProcess()->setTty($tty ? SymfonyProcess::isTtySupported() : $tty);
        $process->run(
            static function (string $type, string $buffer): void {
                echo $buffer;
            }
        );

        return $process;
    }
}
