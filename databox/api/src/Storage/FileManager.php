<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Util\FileUtil;
use Doctrine\ORM\EntityManagerInterface;

class FileManager
{
    private EntityManagerInterface $em;
    private FileStorageManager $storageManager;
    private FilePathGenerator $filePathGenerator;

    public function __construct(
        EntityManagerInterface $em,
        FileStorageManager $storageManager,
        FilePathGenerator $filePathGenerator
    ) {
        $this->em = $em;
        $this->storageManager = $storageManager;
        $this->filePathGenerator = $filePathGenerator;
    }

    public function createFile(
        string $storage,
        string $path,
        ?string $type,
        ?int $size,
        ?string $originalName,
        Workspace $workspace
    ): File {
        $file = new File();
        $file->setStorage($storage);
        $file->setType($type);
        $file->setSize($size);
        $file->setPath($path);
        $file->setWorkspace($workspace);
        $file->setOriginalName($originalName);

        if ($originalName) {
            $file->setExtension(FileUtil::getExtensionFromPath($originalName));
        } elseif ($file->getType()) {
            $extension = FileUtil::getExtensionFromType($file->getType());
            if (!empty($extension)) {
                $file->setExtension($extension);
            }
        }

        $this->em->persist($file);

        return $file;
    }

    public function storeFile(Workspace $workspace, string $src, ?string $type, ?string $extension, ?string $originalName): string
    {
        if (null === $extension) {
            $extension = FileUtil::guessExtension($type, $originalName);
        }

        $path = $this->filePathGenerator->generatePath($workspace->getId(), $extension);

        $fd = fopen($src, 'r');
        $this->storageManager->storeStream($path, $fd);
        fclose($fd);

        return $path;
    }

    public function createFileFromPath(Workspace $workspace, string $src, ?string $type, ?string $extension, ?string $originalName): File
    {
        $path = $this->storeFile($workspace, $src, $type, $extension, $originalName);

        return $this->createFile(
            File::STORAGE_S3_MAIN,
            $path,
            $type,
            filesize($src),
            $originalName,
            $workspace
        );
    }
}
