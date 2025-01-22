<?php

declare(strict_types=1);

namespace Magephi\Component;

use Nette\Utils\Json as NetteJson;
use Nette\Utils\JsonException;
use Symfony\Component\Filesystem\Exception\IOException;

class Json
{
    /**
     * Read json data from a path.
     *
     * @throws JsonException
     */
    public function getContent(string $filepath): array
    {
        $content = @file_get_contents($filepath);

        if (false === $content) {
            $error = error_get_last();

            throw new IOException(
                \sprintf('Failed to read file "%s", "%s".', $filepath, null !== $error ? $error['message'] : 'no reason available'),
                0,
                null,
                $filepath
            );
        }

        return NetteJson::decode($content, forceArrays: true);
    }

    /**
     * Save the data in json format.
     *
     * @throws JsonException
     */
    public function putContent(array $content, string $filepath): bool
    {
        $content = NetteJson::encode($content, pretty: true);

        if (false === @file_put_contents($filepath, $content)) {
            $error = error_get_last();

            throw new IOException(
                \sprintf('Failed to write file "%s", "%s".', $filepath, null !== $error ? $error['message'] : 'no reason available'),
                0,
                null,
                $filepath
            );
        }

        return true;
    }
}
