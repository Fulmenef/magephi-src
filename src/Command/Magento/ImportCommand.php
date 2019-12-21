<?php

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Magephi\Helper\Installation;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to import a database dump. The MySQL container must be started.
 */
class ImportCommand extends AbstractMagentoCommand
{
    protected $command = 'import';

    /** @var Installation */
    protected $installation;

    /**
     * ImportCommand constructor.
     *
     * @param ProcessFactory $processFactory
     * @param DockerCompose  $dockerCompose
     * @param Installation   $installation
     * @param null|string    $name
     */
    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Installation $installation,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->installation = $installation;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Filename to the (possibly compressed) SQL file to import')
            ->addArgument(
                'database',
                InputArgument::OPTIONAL,
                "Destination database. If no database provided, the database defined in docker/local/.env will be used. If the database does not exist, it'll be created."
            )
            ->setDescription('Import a SQL file into a database inside the MySQL Container.')
            ->setHelp('This command allow you to import a SQL file');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->installation->setOutputInterface($output);
        parent::initialize($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = new Environment();

        $file = $this->convertToString($input->getArgument('file'));

        $database = $this->convertToString($input->getArgument('database'));
        if (empty($database)) {
            $database = $environment->getDefaultDatabase();
        }

        if ($database === '') {
            throw new \InvalidArgumentException(
                "The database is not defined. Ensure a database is defined in {$environment->__get('localEnv')} or provide one in the command."
            );
        }

        try {
            $process = $this->installation->databaseImport($database, $file);
        } catch (\Exception $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        if (!$process->isSuccessful()) {
            $this->interactive->error($process->getOutput());
            $this->interactive->error($process->getErrorOutput());

            return AbstractCommand::CODE_ERROR;
        }
        $this->interactive->success(
            "The dump has been imported in {$database} in {$process->getDuration()} seconds"
        );

        return AbstractCommand::CODE_SUCCESS;
    }

    /**
     * @param mixed $string
     *
     * @throws InvalidTypeException
     *
     * @return string
     */
    private function convertToString($string): string
    {
        if (!\is_string($string)) {
            return '';
        }

        return $string;
    }
}
