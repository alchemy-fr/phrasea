<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Storage\FileManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileUploadManager
{
    private FileManager $fileManager;

    public function __construct(
        FileManager $fileManager
    ) {
        $this->fileManager = $fileManager;
    }

    public function storeFileUploadFromRequest(Workspace $workspace, UploadedFile $uploadedFile): File
    {
        ini_set('max_execution_time', '600');

        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        return $this->fileManager->createFileFromPath(
            $workspace,
            $uploadedFile->getRealPath(),
            $uploadedFile->getMimeType(),
            null,
            $uploadedFile->getClientOriginalName()
        );
    }
}
