<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MultipartUpload;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MultipartUploadPartAction extends AbstractController
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        $this->uploadManager = $uploadManager;
    }

    public function __invoke(MultipartUpload $data, Request $request)
    {
        $part = $request->request->get('part');
        if (empty($part)) {
            throw new BadRequestHttpException('Missing part');
        }

        $uri = $this->uploadManager->getSignedUrl($data->getUploadId(), $data->getPath(), (int) $part);

        return new JsonResponse([
            'url' => $uri,
        ]);
    }
}
