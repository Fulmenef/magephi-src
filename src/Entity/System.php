<?php

declare(strict_types=1);

namespace Magephi\Entity;

class System
{
    /** @var array<string, array{mandatory: bool, check: string, comment: ?string}> */
    private array $binaries = [
        'Docker'            => ['mandatory' => true, 'check' => 'docker', 'comment' => null],
        'Docker-Compose'    => ['mandatory' => true, 'check' => 'docker-compose', 'comment' => null],
        'Magento Cloud CLI' => [
            'mandatory' => false,
            'check'     => 'magento-cloud',
            'comment'   => 'Recommended when working on a Magento Cloud project.',
        ],
        'Pipe Viewer'       => [
            'mandatory' => false,
            'check'     => 'pv',
            'comment'   => 'Necessary to display progress during database import.',
        ],
    ];

    /** @var array<string, array{mandatory: bool, check: string, comment: ?string}> */
    private array $services = [
        'Docker'  => ['mandatory' => true, 'check' => 'docker info > /dev/null 2>&1', 'comment' => null],
    ];

    /**
     * Get all prerequisites.
     *
     * @return array{
     *      binaries: array<string, array{mandatory: bool, status: bool, comment: ?string}>,
     *      services: array<string, array{mandatory: bool, status: bool, comment: ?string}>
     *  }
     */
    public function getAllPrerequisites(): array
    {
        $binaries = $this->getBinaryPrerequisites();
        $services = $this->getServicesPrerequisites();

        return ['binaries' => $binaries, 'services' => $services];
    }

    /**
     * Return the mandatory prerequisite no matter if it's a binary or service.
     *
     * @return array{
     *      binaries: array<string, array{mandatory: bool, status: bool, comment: ?string}>,
     *      services: array<string, array{mandatory: bool, status: bool, comment: ?string}>
     *  }
     */
    public function getMandatoryPrerequisites(): array
    {
        $allPrerequisites = $this->getAllPrerequisites();

        /**
         * @var array{
         *      binaries: array<string, array{mandatory: bool, status: bool, comment: ?string}>,
         *      services: array<string, array{mandatory: bool, status: bool, comment: ?string}>
         *  } $systemPrerequisites
         */
        $systemPrerequisites = [];
        foreach ($allPrerequisites as $type => $prerequisites) {
            $filtered = array_filter(
                $prerequisites,
                static function ($array) {
                    return $array['mandatory'];
                }
            );
            if (!empty($filtered)) {
                $systemPrerequisites[$type] = $filtered;
            }
        }

        return $systemPrerequisites;
    }

    /**
     * Return the optional prerequisite no matter if it's a binary or service.
     *
     * @return array{
     *      binaries: array<string, array{mandatory: bool, status: bool, comment: ?string}>,
     *      services: array<string, array{mandatory: bool, status: bool, comment: ?string}>
     *  }
     */
    public function getOptionalPrerequisites(): array
    {
        $allPrerequisites = $this->getAllPrerequisites();

        /**
         * @var array{
         *      binaries: array<string, array{mandatory: bool, status: bool, comment: ?string}>,
         *      services: array<string, array{mandatory: bool, status: bool, comment: ?string}>
         *  } $systemPrerequisites
         */
        $systemPrerequisites = [];
        foreach ($allPrerequisites as $type => $prerequisites) {
            $filtered = array_filter(
                $prerequisites,
                static function ($array) {
                    return !$array['mandatory'];
                }
            );
            if (!empty($filtered)) {
                $systemPrerequisites[$type] = $filtered;
            }
        }

        return $systemPrerequisites;
    }

    /**
     * Return the binary prerequisites, replace the `check` entry by the `status` determined by the return value of
     * the check.
     *
     * @return array<string, array{mandatory: bool, status: bool, comment: ?string}>
     */
    public function getBinaryPrerequisites(): array
    {
        $binaries = [];
        foreach ($this->binaries as $name => $binary) {
            $binaries[$name] = $this->getBinaryStatus($binary);
        }

        return $binaries;
    }

    /**
     * Return the service prerequisites, replace the `check` entry by the `status` determined by the return value of
     * the check.
     *
     * @return array<string, array{mandatory: bool, status: bool, comment: ?string}>
     */
    public function getServicesPrerequisites(): array
    {
        $services = [];
        foreach ($this->services as $name => $service) {
            $services[$name] = $this->getServiceStatus($service);
        }

        return $services;
    }

    /**
     * Check if the givne binary is installed.
     *
     * @param string $binary
     *
     * @return bool Return true if the binary is installed
     */
    public function isInstalled(string $binary): bool
    {
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command = "where {$binary}";
        } else {
            $command = "command -v {$binary}";
        }

        return $this->test($command);
    }

    /**
     * @param array{mandatory: bool, check: string, comment: ?string} $binary
     *
     * @return array{mandatory: bool, status: bool, comment: ?string}
     */
    protected function getBinaryStatus(array $binary): array
    {
        $binary['status'] = $this->isInstalled($binary['check']);
        unset($binary['check']);

        return $binary;
    }

    /**
     * @param array{mandatory: bool, check: string, comment: ?string} $service
     *
     * @return array{mandatory: bool, status: bool, comment: ?string}
     */
    protected function getServiceStatus(array $service): array
    {
        $service['status'] = $this->test($service['check']);
        unset($service['check']);

        return $service;
    }

    /**
     * Execute the shell command, return true if everything went fine.
     *
     * @param string $check
     *
     * @return bool
     */
    private function test(string $check): bool
    {
        exec($check, $output, $returnVar);

        return $returnVar === 0;
    }
}
