<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MultipartUploadStartAction extends AbstractController
{
    private UploadManager $uploadManager;
    private FileStorageManager $storageManager;

    public function __construct(
        UploadManager $uploadManager,
        FileStorageManager $storageManager
    ) {
        $this->uploadManager = $uploadManager;
        $this->storageManager = $storageManager;
    }

    public function __invoke(Request $request)
    {
        $filename = $request->request->get('filename');
        if (empty($filename)) {
            throw new BadRequestHttpException('Missing filename');
        }
        $type = $request->request->get('type');
        if (empty($type)) {
            throw new BadRequestHttpException('Missing type');
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = $this->storageManager->generatePath($extension);

        $uploadData = $this->uploadManager->prepareMultipartUpload($path, $type);
        $uploadId = $uploadData->get('UploadId');

        return new JsonResponse([
            'uploadId' => $uploadId,
            'path' => $path,
        ]);
    }
}
