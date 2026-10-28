<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Attribute\Hierarchy\EntityHierarchyManager;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * An entity having children in the hierarchy is not removed: its subtree would lose its place.
 */
final readonly class DeleteAttributeEntityProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityHierarchyManager $hierarchyManager,
    ) {
    }

    /**
     * @param AttributeEntity $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->hierarchyManager->assertRemovable($data);

        $list = $data->getList();
        if ($list->getRootEntity() === $data) {
            $list->setRootEntity(null);
        }

        $this->em->remove($data);
        $this->em->flush();

        return null;
    }
}
