<?php

declare(strict_types=1);

namespace Magephi\Component;

use Nette\Utils\Json as NetteJson;
use Nette\Utils\JsonException;
use Safe\Exceptions\FilesystemException;

class Json
{
    /**
     * Read json data from a path.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function getContent(string $filepath): array
    {
        $content = \Safe\file_get_contents($filepath);

        return NetteJson::decode($content, NetteJson::FORCE_ARRAY);
    }

    /**
     * Save the data in json format.
     *
     * @throws FilesystemException
     * @throws JsonException
     */
    public function putContent(array $content, string $filepath): bool
    {
        $content = NetteJson::encode($content, NetteJson::PRETTY);

        return (bool) \Safe\file_put_contents($filepath, $content);
    }
}
