<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AttributeEntity;
use App\Service\Image\ImageSetManager;
use App\Service\Storage\MultipartUploadResolver;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UploadAttributeEntityImageProcessor implements ProcessorInterface
{
    private const int MAX_SIZE = 20 * 1024 * 1024;

    public function __construct(
        private EntityManagerInterface $em,
        private MultipartUploadResolver $multipartUploadResolver,
        private ImageSetManager $imageSetManager,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AttributeEntity
    {
        $upload = $this->multipartUploadResolver->resolveFromRequest($context['request']);

        $data->setImage($this->imageSetManager->createFromUpload($upload, $data->getWorkspace(), self::MAX_SIZE));
        $this->em->flush();

        return $data;
    }
}
