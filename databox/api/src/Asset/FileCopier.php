<?php

declare(strict_types=1);

namespace App\Asset;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Util\FileUtil;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Storage\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FileCopier
{
    public function __construct(private EntityManagerInterface $em, private FileStorageManager $storageManager, private FilePathGenerator $pathGenerator)
    {
    }

    public function copyFile(File $file, Workspace $workspace): File
    {
        $copy = $this->copyFileProperties($file, $workspace);

        if (File::STORAGE_S3_MAIN === $file->getStorage()) {
            $stream = $this->storageManager->getStream($file->getPath());
            $extension = $file->getExtension();
            if (null === $extension) {
                $extension = FileUtil::guessExtension($file->getType(), $file->getPath());
            }

            $path = $this->pathGenerator->generatePath($workspace->getId(), $extension);
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
