<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadInput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\UploadManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves the S3 multipart upload referenced by the request payload
 * ({"multipart": {"uploadId": "...", "parts": [...]}}) and finalizes it on S3.
 *
 * The upload must belong to the authenticated user and must not have been
 * used yet: every resource gets its own S3 object.
 */
final readonly class MultipartUploadResolver
{
    public function __construct(
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

        return $this->uploadManager->handleMultipartUpload($input, $ownerId);
    }
}
