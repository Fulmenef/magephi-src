<?php

namespace Magephi\Component;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml as YamlService;

class Yaml
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * Return content of yaml file in array format.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function read(string $filepath): array
    {
        // Method Magephi\Component\Yaml::read() should return array<string, array<string, array<string, string>>> but returns mixed.
        // @phpstan-ignore-next-line
        return YamlService::parseFile($filepath) ?? [];
    }

    /**
     * Write the array in a yamll file.
     *
     * @param array<string, array<string, array<string, string>>> $content
     */
    public function write(string $filepath, array $content): void
    {
        $yaml = YamlService::dump($content);

        $this->filesystem->dumpFile($filepath, $yaml);
    }

    /**
     * Merge current content of the given file with the nex content.
     *
     * @param array<string, array<string, array<string, string>>> $content
     */
    public function update(string $filepath, array $content): void
    {
        $existing = $this->read($filepath);
        $updated = array_merge($existing, $content);

        $this->write($filepath, $updated);
    }

    /**
     * Remove an entry from the file based on the path
     * Example:
     * $existing = [
     *      'key' => [
     *          'value1' => 'value2'
     *      ]
     * ].
     *
     * To remove $existing['key']['value1'], one must provide $path as ['key' => 'value1']
     * Nothing else is supported at the moment
     *
     * @param array<string,string> $path
     */
    public function remove(string $filepath, array $path): void
    {
        $content = $this->read($filepath);

        foreach ($path as $key1 => $key2) {
            if (isset($content[$key1][$key2])) {
                unset($content[$key1][$key2]);
            }
        }

        $this->update($filepath, $content);
    }
}
