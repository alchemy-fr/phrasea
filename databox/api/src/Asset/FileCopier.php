<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Storage\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;

class FileCopier
{
    private EntityManagerInterface $em;
    private FileStorageManager $storageManager;
    private FilePathGenerator $pathGenerator;

    public function __construct(
        EntityManagerInterface $em,
        FileStorageManager $storageManager,
        FilePathGenerator $pathGenerator
    ) {
        $this->em = $em;
        $this->storageManager = $storageManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function copyFile(File $file, Workspace $workspace): File
    {
        $copy = $this->copyFileProperties($file, $workspace);

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $stream = $this->storageManager->getStream($file->getPath());
            $path = $this->pathGenerator->generatePath($workspace->getId(), $file->getExtension());
            $this->storageManager->storeStream($path, $stream);
            $copy->setPath($path);
        }

        $this->em->persist($copy);

        return $copy;
    }

    public function copyFileProperties(File $file, Workspace $workspace): File
    {
        $copy = new File();
        $copy->setType($file->getType());
        $copy->setWorkspace($workspace);
        $copy->setAlternateUrls($file->getAlternateUrls());
        $copy->setPathPublic($file->isPathPublic());
        $copy->setStorage($file->getStorage());
        $copy->setSize($file->getSize());
        $copy->setOriginalName($file->getOriginalName());
        $copy->setExtension($file->getExtension());
        $copy->setPath($file->getPath());

        $this->em->persist($copy);

        return $copy;
    }
}
