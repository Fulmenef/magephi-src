<?php

namespace Magphi\Command\Magento;

use Magphi\Component\DockerCompose;
use Magphi\Component\ProcessFactory;
use Magphi\Helper\Installation;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckPrerequisiteCommand extends AbstractMagentoCommand
{
    protected $command = 'prerequisites';

    /** @var Installation */
    private $installation;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Installation $installation,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->installation = $installation;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Check if all prerequisites are installed on the system to run a Magento 2 project.')
            ->setHelp('This command allows you to know if your system is ready to handle Magento 2 projects.')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $table = new Table($output);
        $table->setHeaders(['Component', 'Mandatory', 'Status', 'Comment']);

        $ready = true;
        foreach ($this->installation->checkSystemPrerequisites() as $component => $info) {
            if (!$info['status']) {
                $ready = false;
            }
            $info['mandatory'] = $info['mandatory'] ? 'Yes' : 'No';
            $info['status'] = $info['status'] ? 'Installed' : 'Missing';
            $table->addRow(
                array_merge([$component], $info)
            );
        }

        $table->render();

        if ($ready) {
            $this->interactive->success('Ready perfectely.');

            return null;
        }
        $this->interactive->error('Your system is not ready yet, install the missing components.');

        return 1;
    }
}
