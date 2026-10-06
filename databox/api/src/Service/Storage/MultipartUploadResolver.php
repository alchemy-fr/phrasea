<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\UploadManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the S3 multipart upload referenced by the request payload
 * ({"multipart": {"uploadId": "...", "parts": [...]}}) and finalizes it
 * on S3 unless it was already completed (idempotent retries).
 */
final readonly class MultipartUploadResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private UploadManager $uploadManager,
    ) {
    }

    public function resolveFromRequest(Request $request): MultipartUpload
    {
        $input = MultipartUploadInput::fromArray($request->toArray()['multipart'] ?? []);

        $upload = $this->em->find(MultipartUpload::class, $input->uploadId);
        if (!$upload instanceof MultipartUpload) {
            throw new NotFoundHttpException(sprintf('Multipart upload "%s" not found', $input->uploadId));
        }

        if (!$upload->isComplete()) {
            $upload = $this->uploadManager->handleMultipartUpload($input);
        }

        return $upload;
    }
}
