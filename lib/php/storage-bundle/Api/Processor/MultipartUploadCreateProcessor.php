<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Api\Processor;

use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Upload\MultipartUploadGuard;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Applies the limits of MultipartUploadGuard, then persists the upload: the
 * upload is created on S3 and its part URLs signed when it is persisted
 * (MultipartUploadListener).
 */
final readonly class MultipartUploadCreateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private MultipartUploadGuard $guard,
        private Security $security,
    ) {
    }

    /**
     * @param MultipartUpload $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MultipartUpload
    {
        $user = $this->security->getUser() ?? throw new AccessDeniedHttpException('Authentication required');

        $this->guard->assertCanCreate($data, $user);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
