<?php

declare(strict_types=1);

namespace App\Controller;

use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MultipartUploadCancelAction extends AbstractController
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        $this->uploadManager = $uploadManager;
    }

    public function __invoke(Request $request)
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
}
