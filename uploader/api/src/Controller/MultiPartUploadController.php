<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/upload")
 */
final class MultiPartUploadController extends AbstractController
{
    private UploadManager $uploadManager;
    private FileStorageManager $storageManager;
    private AssetManager $assetManager;

    public function __construct(
        UploadManager $uploadManager,
        FileStorageManager $storageManager,
        AssetManager $assetManager
    )
    {
        $this->uploadManager = $uploadManager;
        $this->storageManager = $storageManager;
        $this->assetManager = $assetManager;
    }

    /**
     * @Route("/start", methods={"POST"})
     */
    public function start(Request $request)
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

    /**
     * @Route("/stop", methods={"POST"})
     */
    public function stop(Request $request)
    {
        $uploadId = $request->request->get('uploadId');
        if (empty($uploadId)) {
            throw new BadRequestHttpException('Missing uploadId');
        }
        $path = $request->request->get('path');
        if (empty($path)) {
            throw new BadRequestHttpException('Missing path');
        }

        $this->uploadManager->cancelMultipartUpload($path, $uploadId);

        return new JsonResponse(true);
    }

    /**
     * @Route("/url", methods={"POST"})
     */
    public function getUploadUrl(Request $request)
    {
        $filename = $request->request->get('filename');
        if (empty($filename)) {
            throw new BadRequestHttpException('Missing filename');
        }
        $part = $request->request->get('part');
        if (empty($part)) {
            throw new BadRequestHttpException('Missing part');
        }
        $uploadId = $request->request->get('uploadId');
        if (empty($uploadId)) {
            throw new BadRequestHttpException('Missing uploadId');
        }

        $uri = $this->uploadManager->getSignedUrl($uploadId, $filename, $part);

        return new JsonResponse([
            'url' => $uri,
        ]);
    }
}
