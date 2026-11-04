<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DeleteAttributeEntityImageProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (null !== $data->getImage()) {
            // Orphan removal deletes the image and schedules its files
            $data->setImage(null);
            $this->em->flush();
        }

        return null;
    }
}
