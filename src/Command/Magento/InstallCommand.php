<?php

declare(strict_types=1);

namespace Magephi\Command\Magento;

use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends AbstractMagentoCommand
{
    protected string $command = 'install';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Install the Magento2 project in the current directory.')
            ->setHelp('This command allows you to install a basic Magento 2 project in the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = $this->manager->getEnvironment();

        $command = sprintf(
            "bin/magento setup:install
            --base-url=%s
            --db-host=%s
            --db-name=%s
            --db-user=%s
            --db-password='%s'
            --backend-frontname=%s
            --admin-firstname=%s
            --admin-lastname=%s
            --admin-email=%s
            --admin-user=%s
            --admin-password=%s
            --language=%s
            --currency=%s
            --timezone=%s
            --use-rewrites=%d
            --search-engine=%s
            --elasticsearch-host=%s
            --elasticsearch-port=%s
            --session-save=%s
            --session-save-redis-host=%s
            --session-save-redis-port=%d
            --session-save-redis-password=%s
            --session-save-redis-timeout=%d
            --session-save-redis-persistent-id=%s
            --session-save-redis-db=%s
            --session-save-redis-compression-threshold=%s
            --session-save-redis-compression-lib=%s
            --session-save-redis-log-level=%s
            --session-save-redis-max-concurrency=%d
            --session-save-redis-break-after-frontend=%s
            --session-save-redis-break-after-adminhtml=%s
            --session-save-redis-first-lifetime=%d
            --session-save-redis-bot-first-lifetime=%d
            --session-save-redis-bot-lifetime=%d
            --session-save-redis-disable-locking=%s
            --session-save-redis-min-lifetime=%d
            --session-save-redis-max-lifetime=%d
            --session-save-redis-sentinel-master=%s
            --session-save-redis-sentinel-servers=%s
            --session-save-redis-sentinel-verify-master=%s
            --session-save-redis-sentinel-connect-retries=%d",
            $environment->getServerName(true),
            'mysql',
            $environment->getDatabase(),
            $environment->getEnvData('mysql_user'),
            $environment->getEnvData('mysql_password'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What must be the backend frontname ?', 'admin'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the admin firstname ?', 'admin'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the admin lastname ?', 'admin'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the admin email ?', 'admin@admin.com'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the admin username ?', 'admin'),
            // @phpstan-ignore-next-line
            $this->interactive->ask(
                'What is the admin password ?',
                '4dM7NPwd',
                function ($answer) {
                    if (empty($answer)) {
                        throw new \RuntimeException(
                            'The password cannot be empty.'
                        );
                    }
                    if (\strlen($answer) < 7) {
                        throw new \RuntimeException(
                            'The password must be at least 7 characters.'
                        );
                    }
                    if (preg_replace('![^A-Z]+!', '', $answer) === '') {
                        throw new \RuntimeException(
                            'The password must include uppercase characters.'
                        );
                    }
                    if (\strlen(preg_replace('![^0-9]+!', '', $answer)) < 2) {
                        throw new \RuntimeException(
                            'The password must include numerical characters.'
                        );
                    }

                    return $answer;
                }
            ),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the project default language ?', 'en_US'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the project default currency ?', 'USD'),
            // @phpstan-ignore-next-line
            $this->interactive->ask('What is the project default timezone ?', 'America/Chicago'),
            $this->interactive->confirm('Do you want to use url rewrites ?', true) ? 1 : 0,
            // @phpstan-ignore-next-line
            $this->interactive->ask('What search engine do you want ?', 'elasticsearch7'),
            'elasticsearch',
            9200,
            'redis',
            'redis',
            6379,
            '',
            2.5,
            '',
            0,
            2048,
            'gzip',
            4,
            6,
            5,
            30,
            600,
            60,
            7200,
            0,
            60,
            2592000,
            '',
            '',
            0,
            5
        );

        try {
            $this->dockerCompose->executeContainerCommand('php', 'mkdir pub/static');
            $this->dockerCompose->executeContainerCommand('php', 'composer dumpautoload');
            $this->dockerCompose->executeContainerCommand('php', 'rm -rf generated');
            $this->interactive->section('Installation');

            if ($_ENV['SHELL_VERBOSITY'] >= 1) {
                $install = $this->dockerCompose->executeContainerCommand('php', $command);
            } else {
                $install = $this->dockerCompose->executeContainerCommand('php', $command, true);
                $progressBar = new ProgressBar($output, 0);
                $regex = '/Progress: (\d*) \/ (\d*)/i';

                $install->start();
                $progressBar->start();
                $install->getProcess()->waitUntil(
                    function (string $type, string $buffer) use ($regex, $progressBar) {
                        preg_match($regex, $buffer, $match);
                        if (isset($match[1])) {
                            if ($progressBar->getMaxSteps() !== $match[2]) {
                                $progressBar->setMaxSteps((int) $match[2]);
                            }
                            $progressBar->setProgress((int) $match[1]);
                        }

                        return false;
                    }
                );

                if ($install->getProcess()->isSuccessful()) {
                    $progressBar->finish();
                }
            }
        } catch (EnvironmentException|ProcessException $e) {
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        $this->interactive->newLine(2);

        if (!$install->getProcess()->isSuccessful()) {
            $this->interactive->error('An error occurred during installation');
            $error = explode(PHP_EOL, $install->getProcess()->getErrorOutput());

            // Clean error to have a smaller one
            for ($i = 0; $i < 5; ++$i) {
                array_pop($error);
            }

            $this->interactive->error(implode(PHP_EOL, $error));

            return self::FAILURE;
        }

        $this->interactive->success(
            sprintf(
                'Magento installed, you can access to your website with the url %s',
                $environment->getServerName(true)
            )
        );

        return self::SUCCESS;
    }
}
