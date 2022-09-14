<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Border\Exception\FileInputValidationException;
use App\Border\Model\FileContent;
use App\Border\Model\InputFile;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Storage\FilePathGenerator;
use Doctrine\ORM\EntityManagerInterface;

class BorderManager
{
    private EntityManagerInterface $em;
    private FileDownloader $fileDownloader;
    private FileStorageManager $storageManager;
    private FilePathGenerator $pathGenerator;

    public function __construct(
        EntityManagerInterface $em,
        FileDownloader $fileDownloader,
        FileStorageManager $storageManager,
        FilePathGenerator $pathGenerator
    ) {
        $this->em = $em;
        $this->fileDownloader = $fileDownloader;
        $this->storageManager = $storageManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function acceptFile(InputFile $inputFile, Workspace $workspace): ?File
    {
        try {
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
        } catch (FileInputValidationException $e) {
            return null;
        }
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
