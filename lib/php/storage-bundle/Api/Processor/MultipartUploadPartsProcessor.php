<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Processor;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadPartsOutput;
use Alchemy\StorageBundle\Doctrine\MultipartUploadListener;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\MultipartUploadGuard;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns the presigned upload URLs of the remaining parts of an upload, at
 * most MultipartUploadListener::MAX_SIGNED_URLS from "from".
 */
final readonly class MultipartUploadPartsProcessor implements ProcessorInterface
{
    public function __construct(
        private UploadManager $uploadManager,
        private MultipartUploadPlanner $planner,
        private MultipartUploadGuard $guard,
        private Security $security,
    ) {
    }

    /**
     * @param MultipartUpload $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipartUploadPartsOutput
    {
        if ($data->isComplete()) {
            throw new BadRequestHttpException('Upload is already complete');
        }

        $from = $context['request']->request->get('from', 1);
        if (!is_int($from) && !(is_string($from) && ctype_digit($from))) {
            throw new BadRequestHttpException('"from" must be a positive integer');
        }
        $from = (int) $from;
        if ($from < 1) {
            throw new BadRequestHttpException('"from" must be greater than or equal to 1');
        }

        $this->guard->assertCanSignParts($this->security->getUser() ?? throw new AccessDeniedHttpException('Authentication required'));

        // Uploads created before the chunk size was persisted fall back to the current policy.
        $chunkSize = $data->getChunkSize() ?? $this->planner->resolveChunkSize($data->getSize());
        $partCount = $this->planner->getPartCount($data->getSize(), $chunkSize);

        return new MultipartUploadPartsOutput(
            chunkSize: $chunkSize,
            partCount: $partCount,
            // Past the last part there is nothing left to upload (e.g. resuming right before completion)
            urls: $from <= $partCount
                ? $this->uploadManager->getSignedUrls($data->getUploadId(), $data->getPath(), $from, min($partCount, $from + MultipartUploadListener::MAX_SIGNED_URLS - 1))
                : [],
        );
    }
}
