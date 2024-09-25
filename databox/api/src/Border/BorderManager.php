<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Border\Model\FileContent;
use App\Border\Model\InputFile;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Storage\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BorderManager
{
    public function __construct(private EntityManagerInterface $em, private UriDownloader $fileDownloader, private FileStorageManager $storageManager, private FilePathGenerator $pathGenerator)
    {
    }

    public function acceptFile(InputFile $inputFile, Workspace $workspace): File
    {
        $this->validateFile($inputFile);

        $localFilePath = $this->importFile($inputFile);

        $content = new FileContent($inputFile, $localFilePath);
        $this->validateContent($content);

        $finalPath = $this->pathGenerator->generatePath($workspace->getId(), $inputFile->getExtension());

        $fd = fopen($content->getPath(), 'r');
        $this->storageManager->storeStream($finalPath, $fd);
        fclose($fd);

        unlink($content->getPath());

        $file = new File();
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath($finalPath);
        $file->setOriginalName($inputFile->getName());
        $file->setExtension($inputFile->getExtension());
        $file->setSize($inputFile->getSize());
        $file->setType($inputFile->getType());
        $file->setWorkspace($workspace);

        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function validateFile(InputFile $file): void
    {
    }

    public function validateContent(FileContent $fileContent): void
    {
    }

    private function importFile(InputFile $file): string
    {
        return $this->fileDownloader->download($file->getUrl());
    }
}
