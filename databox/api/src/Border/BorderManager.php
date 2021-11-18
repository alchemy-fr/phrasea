<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Border\Exception\FileInputValidationException;
use App\Border\Model\FileContent;
use App\Border\Model\InputFile;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class BorderManager
{
    private EntityManagerInterface $em;
    private FileDownloader $fileDownloader;
    private FileStorageManager $storageManager;

    public function __construct(
        EntityManagerInterface $em,
        FileDownloader $fileDownloader,
        FileStorageManager $storageManager
    )
    {
        $this->em = $em;
        $this->fileDownloader = $fileDownloader;
        $this->storageManager = $storageManager;
    }

    public function acceptFile(InputFile $inputFile, Workspace $workspace): ?File
    {
        try {
            $this->validateFile($inputFile);

            $localFilePath = $this->importFile($inputFile);

            $content = new FileContent($inputFile, $localFilePath);
            $this->validateContent($content);

            $finalPath = sprintf('files/%s/%s/%s%s',
                $workspace->getId(),
                date('Y/m/d'),
                Uuid::uuid4(),
                $inputFile->getExtensionWithDot()
            );

            $fd = fopen($content->getPath(), 'r');
            $this->storageManager->storeStream($finalPath, $fd);
            fclose($fd);

            $file = new File();
            $file->setPath($finalPath);
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
