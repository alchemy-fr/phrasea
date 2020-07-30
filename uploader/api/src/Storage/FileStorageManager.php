<?php

declare(strict_types=1);

namespace App\Storage;

use League\Flysystem\FilesystemInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class FileStorageManager
{
    private FilesystemInterface $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function generatePath(?string $extension): string
    {
        $uuid = Uuid::uuid4()->toString();

        $path = implode(DIRECTORY_SEPARATOR, [
            substr($uuid, 0, 2),
            substr($uuid, 2, 2),
            $uuid,
        ]);

        if ($extension) {
            $path .= '.'.$extension;
        }

        return $path;
    }

    public function store(string $path, $content): void
    {
        $this->filesystem->write($path, $content);
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
            throw new RuntimeException(sprintf('Cannot read at "%s"', $path));
        }

        return $resource;
    }
}
