<?php

declare(strict_types=1);

namespace App\Http;

use Alchemy\StorageBundle\Upload\FileValidator;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Service\Storage\FileManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class FileUploadManager
{
    public function __construct(
        private FileManager $fileManager,
        private FileValidator $fileValidator,
    ) {
    }

    public function storeUploadedFile(Workspace $workspace, UploadedFile $uploadedFile): File
    {
        ini_set('max_execution_time', '600');

        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $this->fileValidator->validateFileContent(
            $uploadedFile->getRealPath(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getMimeType(),
        );

        return $this->fileManager->createFileFromPath(
            $workspace,
            $uploadedFile->getRealPath(),
            $uploadedFile->getMimeType(),
            null,
            $uploadedFile->getClientOriginalName(),
            removeSrc: false,
        );
    }
}
