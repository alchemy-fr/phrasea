<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

use League\Flysystem\FilesystemOperator;

final readonly class FileStorageManager
{
    public function __construct(private FilesystemOperator $filesystem)
    {
    }

    public function store(string $path, $content): void
    {
        $this->filesystem->write($path, $content);
    }

    public function has(string $path): bool
    {
        return $this->filesystem->has($path);
    }

    /**
     * @param $content resource
     */
    public function storeStream(string $path, $content): void
    {
        $this->filesystem->writeStream($path, $content);
    }

    public function delete(string $path): void
    {
        $this->filesystem->delete($path);
    }

    public function getStream(string $path)
    {
        $resource = $this->filesystem->readStream($path);
        if (false === $resource) {
            throw new \RuntimeException(sprintf('Cannot read at "%s"', $path));
        }

        return $resource;
    }
}
