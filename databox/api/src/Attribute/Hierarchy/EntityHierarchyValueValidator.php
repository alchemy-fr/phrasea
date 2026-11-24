<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

/**
 * A definition bound to hierarchy depths only accepts the entities of its list within those depths,
 * placeholders excepted.
 */
final readonly class EntityHierarchyValueValidator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return string[]|null
     */
    public function validate(AttributeDefinition $definition, mixed $value): ?array
    {
        if (!$definition->isHierarchical()) {
            return null;
        }
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }
        if (!\is_string($value) || !Uuid::isValid($value)) {
            return null;
        }

        $entity = $this->em->find(AttributeEntity::class, $value);
        if (!$entity instanceof AttributeEntity || $entity->getList() !== $definition->getEntityList()) {
            return [sprintf('The entity does not belong to the list of attribute "%s"', $definition->getName())];
        }
        if ($entity->isPlaceholder()) {
            return ['A hierarchy placeholder cannot be selected'];
        }
        if (!$definition->acceptsHierarchyDepth($entity->getHierarchyDepth())) {
            return [sprintf('"%s" is not at the hierarchy levels of attribute "%s"', $entity->getValue(), $definition->getName())];
        }

        return null;
    }
}
