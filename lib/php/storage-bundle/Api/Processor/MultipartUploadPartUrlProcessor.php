<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Processor;

use Alchemy\StorageBundle\Api\Dto\MultipartUploadPartUrlOutput;
use Alchemy\StorageBundle\Entity\MultipartUpload;
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
    ) {
    }

    /**
     * @param MultipartUpload $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipartUploadPartUrlOutput
    {
        $part = $context['request']->request->get('part');
        if (empty($part)) {
            throw new BadRequestHttpException('Missing part');
        }

        return new MultipartUploadPartUrlOutput(
            $this->uploadManager->getSignedUrl($data->getUploadId(), $data->getPath(), (int) $part),
        );
    }
}
