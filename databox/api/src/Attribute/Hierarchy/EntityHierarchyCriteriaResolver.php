<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use ApiPlatform\Metadata\Operation;
use App\Api\Traits\ParameterValuesTrait;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EntityHierarchyCriteriaResolver
{
    use ParameterValuesTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function resolve(Operation $operation): EntityHierarchyCriteria
    {
        $criteria = new EntityHierarchyCriteria(
            parentId: self::getParameterId($operation, 'parent'),
            depth: self::toInt(self::getParameterValue($operation, 'depth')),
            branchId: self::getParameterId($operation, 'branch'),
            unclassified: self::toBool(self::getParameterValue($operation, 'unclassified')),
            placeholder: self::toBool(self::getParameterValue($operation, 'placeholder')),
        );

        $definitionId = self::getParameterId($operation, 'definition');
        if (null !== $definitionId) {
            $definition = $this->em->find(AttributeDefinition::class, $definitionId);
            if (!$definition instanceof AttributeDefinition || null === $definition->getEntityList()) {
                $criteria->none = true;
            } else {
                $this->applyDefinition($criteria, $definition);
            }
        }

        return $criteria;
    }

    /**
     * The entities a value of the definition may reference.
     */
    public function applyDefinition(EntityHierarchyCriteria $criteria, AttributeDefinition $definition): void
    {
        $criteria->listId = $definition->getEntityList()?->getId();
        if (!$definition->isHierarchical()) {
            return;
        }

        $criteria->depth = $definition->getEntityHierarchyDepth();
        $criteria->placeholder = false;
        if (null !== $branch = $definition->getEntityHierarchyBranch()) {
            $criteria->branchId = $branch->getId();
        }
    }

    private static function toInt(mixed $value): ?int
    {
        if (\is_array($value)) {
            $value = reset($value);
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function toBool(mixed $value): ?bool
    {
        if (null === $value || '' === $value || \is_array($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
