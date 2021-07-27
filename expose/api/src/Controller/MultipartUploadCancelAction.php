<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MultipartUpload;
use App\Upload\UploadManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class MultipartUploadCancelAction extends AbstractController
{
    private UploadManager $uploadManager;

    public function __construct(UploadManager $uploadManager)
    {
        $this->uploadManager = $uploadManager;
    }

    public function __invoke(MultipartUpload $multipartUpload)
    {
        $this->uploadManager->cancelMultipartUpload($multipartUpload->getPath(), $multipartUpload->getUploadId());

        return new JsonResponse(true);
    }
}
