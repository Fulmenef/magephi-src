<?php

declare(strict_types=1);

namespace Magephi\Helper;

use Magephi\Kernel;

class ReleaseHandler
{
    public function __construct(private Kernel $kernel)
    {
    }

    /**
     * Remove previous version caches and logs.
     */
    public function handle(): void
    {
        $customDir = $this->kernel->getCustomDir();

        if (is_dir($customDir)) {
            /** @var string[] $scan */
            $scan = scandir($customDir);

            $diff = array_diff($scan, ['.', '..', $this->kernel->getVersion(), 'config.yml']);
            if (!empty($diff)) {
                foreach ($diff as $directory) {
                    $this->deleteFiles($customDir . '/' . $directory);
                }
            }
        }
    }

    /**
     * Method to remove file and directory recursively.
     */
    public function deleteFiles(string $target): void
    {
        if (is_dir($target)) {
            /** @var string[] $files */
            $files = glob($target . '*', GLOB_MARK); // GLOB_MARK adds a slash to directories returned

            foreach ($files as $file) {
                $this->deleteFiles($file);
            }

            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}
