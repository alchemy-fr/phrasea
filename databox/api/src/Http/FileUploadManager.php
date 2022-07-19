<?php

declare(strict_types=1);

namespace App\Http;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Util\ExtensionUtil;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileUploadManager
{
    private FileStorageManager $storageManager;
    private PathGenerator $pathGenerator;

    public function __construct(
        FileStorageManager $storageManager,
        PathGenerator $pathGenerator
    ) {
        $this->storageManager = $storageManager;
        $this->pathGenerator = $pathGenerator;
    }

    public function storeFileUploadFromRequest(Request $request): ?string
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (null !== $uploadedFile) {
            ini_set('max_execution_time', '600');

            if (!$uploadedFile->isValid()) {
                throw new BadRequestHttpException('Invalid uploaded file');
            }
            if (0 === $uploadedFile->getSize()) {
                throw new BadRequestHttpException('Empty file');
            }

            $extension = ExtensionUtil::guessExtension($uploadedFile->getType(), $uploadedFile->getClientOriginalName());
            $path = $this->pathGenerator->generatePath($extension);

            $stream = fopen($uploadedFile->getRealPath(), 'r+');
            $this->storageManager->storeStream($path, $stream);
            fclose($stream);

            return $path;
        }

        return null;
    }
}
