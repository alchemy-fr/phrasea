<?php

declare(strict_types=1);

namespace App\Storage;

use League\Flysystem\FilesystemInterface;
use Ramsey\Uuid\Uuid;

class FileStorageManager
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

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
            $uuid
        ]);

        if ($extension) {
            $path.= '.' . $extension;
        }

        return $path;
    }

    public function store(string $path, $content): void
    {
        $this->filesystem->write($path, $content);
    }

    /**
     * @var resource
     */
    public function storeStream(string $path, $content): void
    {
        $this->filesystem->writeStream($path, $content);
    }
}
