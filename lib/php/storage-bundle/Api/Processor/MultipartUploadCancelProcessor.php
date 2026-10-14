<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Processor;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\UploadManager;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Aborts the upload on S3, then deletes it.
 */
final readonly class MultipartUploadCancelProcessor implements ProcessorInterface
{
    public function __construct(
        private UploadManager $uploadManager,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    /**
     * @param MultipartUpload $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        try {
            $this->uploadManager->cancelMultipartUpload($data->getPath(), $data->getUploadId());
        } catch (\Throwable) {
            // S3 storage will clean up its uncomplete uploads automatically
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
