<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Entity\Asset;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateAssetAction extends AbstractController
{
    private FileStorageManager $storageManager;
    private AssetManager $assetManager;
    private UploadManager $uploadManager;

    public function __construct(
        FileStorageManager $storageManager,
        AssetManager $assetManager,
        UploadManager $uploadManager
    ) {
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
        $this->uploadManager = $uploadManager;
    }

    public function __invoke(Request $request): Asset
    {
        if ($request->request->get('multipart')) {
            return $this->handleMultipartUpload($request);
        }

        ini_set('max_execution_time', '600');

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException('Invalid uploaded file');
        }
        if (0 === $uploadedFile->getSize()) {
            throw new BadRequestHttpException('Empty file');
        }

        $extension = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION);
        $path = $this->storageManager->generatePath($extension);

        $stream = fopen($uploadedFile->getRealPath(), 'r+');
        $this->storageManager->storeStream($path, $stream);
        fclose($stream);

        /** @var RemoteUser $user */
        $user = $this->getUser();

        return $this->assetManager->createAsset(
            $path,
            $uploadedFile->getMimeType(),
            $uploadedFile->getClientOriginalName(),
            $uploadedFile->getSize(),
            $user->getId()
        );
    }

    private function handleMultipartUpload(Request $request): Asset
    {
        $multipart = $request->request->get('multipart');

        foreach ([
            'parts',
            'filename',
            'path',
            'size',
            'type',
            'uploadId',
                 ] as $key) {
            if (empty($multipart[$key])) {
                throw new BadRequestHttpException(sprintf('Missing multipart param: %s', $key));
            }
        }

        $this->uploadManager->markComplete(
            $multipart['uploadId'],
            $multipart['path'],
            $multipart['parts']
        );

        /** @var RemoteUser $user */
        $user = $this->getUser();

        return $this->assetManager->createAsset(
            $multipart['path'],
            $multipart['type'],
            $multipart['filename'],
            $multipart['size'],
            $user->getId()
        );
    }
}
