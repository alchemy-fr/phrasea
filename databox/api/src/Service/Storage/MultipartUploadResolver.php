<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\UploadManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the S3 multipart upload referenced by the request payload
 * ({"multipart": {"uploadId": "...", "parts": [...]}}) and finalizes it
 * on S3 unless it was already completed (idempotent retries).
 *
 * The upload must belong to the authenticated user, completed or not.
 */
final readonly class MultipartUploadResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private UploadManager $uploadManager,
        private Security $security,
    ) {
    }

    public function resolveFromRequest(Request $request): MultipartUpload
    {
        return $this->resolve(MultipartUploadInput::fromArray($request->toArray()['multipart'] ?? []));
    }

    public function resolve(MultipartUploadInput $input): MultipartUpload
    {
        $ownerId = $this->security->getUser()?->getUserIdentifier()
            ?? throw new AccessDeniedHttpException('User must be authenticated');

        $upload = $this->em->find(MultipartUpload::class, $input->uploadId);
        if (!$upload instanceof MultipartUpload) {
            throw new NotFoundHttpException(sprintf('Multipart upload "%s" not found', $input->uploadId));
        }

        if ($upload->isComplete()) {
            UploadManager::denyUnlessOwner($upload, $ownerId);

            return $upload;
        }

        return $this->uploadManager->handleMultipartUpload($input, $ownerId);
    }
}
