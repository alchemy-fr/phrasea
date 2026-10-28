<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Attribute\Hierarchy\EntityHierarchyManager;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * PATCH of an entity: a new parent or position moves it in the list hierarchy.
 */
final readonly class AttributeEntityHierarchyProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityHierarchyManager $hierarchyManager,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): AttributeEntity
    {
        $original = $this->em->getUnitOfWork()->getOriginalEntityData($data);
        $parentChanged = ($original['parent'] ?? null) !== $data->getParent();
        $positionChanged = ($original['position'] ?? 0) !== $data->getPosition();

        $this->hierarchyManager->place($data, $parentChanged || $positionChanged);

        return $data;
    }
}
