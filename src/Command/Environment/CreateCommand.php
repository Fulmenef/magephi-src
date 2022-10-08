<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Composer\Semver\Comparator;
use ErrorException;
use Exception;
use InvalidArgumentException;
use Magephi\Component\DockerCompose;
use Magephi\Component\Json;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Magephi\Exception\ComposerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class CreateCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'create';

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Manager $manager,
        private LoggerInterface $logger,
        private Json $json
    ) {
        parent::__construct($processFactory, $dockerCompose, $manager);
    }

    public function getPrerequisites(): array
    {
        return ['binary' => ['Docker']];
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Create a project for Magento 2 community or enterprise.')
            ->setHelp(
                'If the current directory is empty, the project will be installed inside. If not, a name will be asked and the project will be installed in a directory named after it.'
            )
            ->addOption(
                'enterprise',
                null,
                InputOption::VALUE_NONE,
                'Specify this option if you want to create a Magento Commerce project.'
            )
            ->addOption(
                'patch',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Specify this option if you want to install a version or specific patch. E.g: 2.3.3, 2.3.3-p1...'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!($currentDir = getcwd()) || !($scan = scandir($currentDir))) {
            throw new DirectoryNotFoundException('Your current directory is not readable', self::FAILURE);
        }

        try {
            $this->initProjectDirectory($currentDir, $scan);
        } catch (ErrorException $e) {
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        $package = $input->getOption(
            'enterprise'
        ) ? 'magento/project-enterprise-edition' : 'magento/project-community-edition';

        /** @var string $patch */
        $patch = $input->getOption('patch');
        if ($patch) {
            $patch = ltrim($patch, '=');
            $package .= "={$patch}";
        }

        $this->processFactory->runInteractiveProcess(
            [
                'composer',
                'create-project',
                '--ignore-platform-reqs',
                '--no-install',
                '--repository=https://repo.magento.com/',
                $package,
                '.',
            ],
            30,
            ['COMPOSER_MEMORY_LIMIT' => '2G']
        );
        $this->logger->info('Based project created');

        try {
            $this->initComposerDev($patch);
        } catch (ComposerException $e) {
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        if (!is_file('package.json') && is_file('package.json.sample')) {
            copy('package.json.sample', 'package.json');
        }

        try {
            $this->initPackageDev();
        } catch (ComposerException|Exception $e) {
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        $this->initGitignore();

        $this->initGitkeep();

        $this->interactive->success('Your project has been created, you can now use the install command.');

        return self::SUCCESS;
    }

    /**
     * Check if the current directory is empty, if not, ask the name of the project to create the directory where the
     * Magento 2 project will be installed.
     *
     * @param string[] $scan
     *
     * @throws ErrorException
     */
    protected function initProjectDirectory(string $currentDir, array $scan): void
    {
        if (\count($scan) > 2) {
            $projectName = $this->interactive->ask('Enter your project name', 'magento2');

            if (!\is_string($projectName)) {
                throw new InvalidArgumentException(sprintf('Project name should be a string, %s given', \gettype($projectName)));
            }

            try {
                mkdir($currentDir . '/' . $projectName);
                chdir($projectName);
            } catch (ErrorException $e) {
                throw new ErrorException('A directory with that name already exist, try again with another name or try somewhere else.');
            }
        }
    }

    /**
     * Use specific dependencies for dev for composer.
     *
     * @param null|string $version Magento version
     */
    protected function initComposerDev(?string $version): void
    {
        $composer = $this->json->getContent('composer.json');

        if (null === $version) {
            $version = $composer['version'];
        }

        $requireDev = [
            'emakinafr/docker-magento2' => '^3.0',
        ];

        if (Comparator::greaterThanOrEqualTo($version, '2.4.4')) {
            $phpVersion = '8.1.0';
        } else {
            $phpVersion = '7.4.0';
            $requireDev['bitexpert/phpstan-magento'] = 'dev-master';
            $requireDev['friendsofphp/php-cs-fixer'] = '^2.0';
        }

        $composer['require-dev'] = $requireDev;

        $config = $composer['config'] ?? [];
        $config['platform'] = [
            'php'           => $phpVersion,
            'ext-bcmath'    => $phpVersion,
            'ext-gd'        => $phpVersion,
            'ext-intl'      => $phpVersion,
            'ext-pdo_mysql' => $phpVersion,
            'ext-soap'      => $phpVersion,
            'ext-xsl'       => $phpVersion,
            'ext-sockets'   => $phpVersion,
        ];
        $config['process-timeout'] = 600;
        $composer['config'] = $config;

        $this->json->putContent($composer, 'composer.json');

        $this->processFactory->runInteractiveProcess(
            [
                'docker',
                'run',
                '--rm',
                '--interactive',
                '--tty',
                '--volume',
                '$PWD:/app',
                '--env',
                'COMPOSER_MEMORY_LIMIT=2G',
                'composer',
                'update',
                '--optimize-autoloader',
            ]
        );
        $this->interactive->comment('Composer dependencies installed');

        $this->processFactory->runProcess(['composer', 'exec', 'docker-local-install']);
    }

    /**
     * Use specific dependencies for dev for yarn.
     */
    protected function initPackageDev(): void
    {
        $package = $this->json->getContent('package.json');
        $content = $package['devDependencies'];

        $requireDev = [
            '@magento/eslint-config'     => '^1.5.0',
            'eslint'                     => '^6.5.1',
            'eslint-config-google'       => '^0.14.0',
            'eslint-config-recommended'  => '^4.0.0',
            'eslint-plugin-jsx-a11y'     => '^6.2.3',
            'eslint-plugin-package-json' => '^0.1.3',
            'eslint-plugin-react'        => '^7.17.0',
            'eslint-plugin-react-hooks'  => '^2.1.2',
            'husky'                      => '^4.0.0-beta.2',
            'lint-staged'                => '^10.0.0-0',
            'stylelint'                  => '^11.1.1',
            'stylelint-config-standard'  => '^19.0.0',
        ];
        $devDependencies = array_merge($requireDev, $content);
        asort($devDependencies);
        $package['devDependencies'] = $devDependencies;
        $this->json->putContent($package, 'package.json');

        $this->processFactory->runInteractiveProcess(
            [
                'docker',
                'run',
                '--rm',
                '--interactive',
                '--tty',
                '--volume',
                '$PWD:/app',
                '--platform=linux/amd64',
                'node',
                'yarn',
                'install',
                '--cwd /app',
            ]
        );

        $this->interactive->comment('Yarn packages installed');
    }

    /**
     * Setup custom content for .gitignore.
     */
    protected function initGitignore(): void
    {
        $gitignore = <<<'EOD'
/app/*
!/app/code/
!/app/design/
!/app/etc
!/app/etc/config.php
/dev/*
/dev/tools/*
/dev/tools/grunt/*
/dev/tools/grunt/configs/*
!/dev/tools
!/dev/tools/grunt
!/dev/tools/grunt/configs
!/dev/tools/grunt/configs/babel.*
!/dev/tools/grunt/configs/local-themes.js
!/dev/tools/grunt/configs/postcss.*
!/docker/local
/.github
/.htaccess
/.htaccess.sample
/.magento.env.yaml.dist
/.php_cs.cache
/.php_cs.dist
/.travis.yml
/.travis.yml.sample
/.user.ini
/app/design/*/Magento
/app/etc/*
/auth.json.sample
/backup.tar
/bin/.htaccess
/bin/magento
/CHANGELOG.md
/COPYING.txt
/docker/*
/docker/local/.env
/generated/
/grunt-config.json.sample
/Gruntfile.js.sample
/index.php
/lib/
/LICENSE*
/nginx.conf.sample
/node_modules
/package.json.sample
/php.ini.sample
/phpserver/
/pub/
/SECURITY.md
/setup/
/update/*
/var/
/vendor/
!/yarn.lock
/yarn-error.log
EOD;

        file_put_contents('.gitignore', $gitignore);
    }

    /**
     * Add .gitkeep file to useful directories.
     */
    protected function initGitkeep(): void
    {
        mkdir('app/code');
        fopen('app/code/.gitkeep', 'w');
        fopen('app/design/.gitkeep', 'w');
        fopen('app/etc/.gitkeep', 'w');
    }
}
