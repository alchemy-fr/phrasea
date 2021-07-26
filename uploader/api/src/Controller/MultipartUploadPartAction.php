<?php

declare(strict_types=1);

namespace App\Controller;

use App\Storage\FileStorageManager;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class MultipartUploadPartAction extends AbstractController
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        $this->uploadManager = $uploadManager;
    }

    public function __invoke(Request $request)
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
