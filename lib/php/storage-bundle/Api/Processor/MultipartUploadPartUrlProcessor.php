<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Processor;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadPartUrlOutput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns the presigned upload URL of a single part (deprecated in favor of MultipartUploadPartsProcessor).
 */
final readonly class MultipartUploadPartUrlProcessor implements ProcessorInterface
{
    public function __construct(
        private UploadManager $uploadManager,
        private MultipartUploadPlanner $planner,
    ) {
    }

    /**
     * @param MultipartUpload $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipartUploadPartUrlOutput
    {
        if ($data->isComplete()) {
            throw new BadRequestHttpException('Upload is already complete');
        }

        $part = $context['request']->request->get('part');
        if (null === $part || '' === $part) {
            throw new BadRequestHttpException('Missing part');
        }
        if (!is_int($part) && !(is_string($part) && ctype_digit($part))) {
            throw new BadRequestHttpException('"part" must be a positive integer');
        }
        $part = (int) $part;

        // Uploads created before the chunk size was persisted fall back to the current policy.
        $chunkSize = $data->getChunkSize() ?? $this->planner->resolveChunkSize($data->getSize());
        $partCount = $this->planner->getPartCount($data->getSize(), $chunkSize);
        if ($part < 1 || $part > $partCount) {
            throw new BadRequestHttpException(sprintf('"part" must be between 1 and %d', $partCount));
        }

        return new MultipartUploadPartUrlOutput(
            $this->uploadManager->getSignedUrl($data->getUploadId(), $data->getPath(), $part),
        );
    }
}
