<?php

declare(strict_types=1);

namespace App\Attribute\Hierarchy;

use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Places the entities of a list in its hierarchy: depth of the moved subtree and order among the siblings.
 * The structural checks (same list, no cycle, classified parent) are the AttributeEntity validation ones.
 */
final readonly class EntityHierarchyManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AttributeEntityRepository $repository,
    ) {
    }

    /**
     * Applies the parent (already set on the entity) and the position, then flushes.
     *
     * @param bool $reorder whether the position was given (or the parent changed)
     */
    public function place(AttributeEntity $entity, bool $reorder): void
    {
        $this->assertPlaceable($entity);

        $depth = $this->computeDepth($entity);
        $depthChanged = $depth !== $entity->getHierarchyDepth();
        $entity->setHierarchyDepth($depth);
        if ($reorder) {
            $this->repository->reorderSiblings($entity, $entity->getPosition());
        }

        $this->em->persist($entity);
        $this->em->flush();

        if ($depthChanged && !$entity->getChildren()->isEmpty()) {
            $this->repository->updateSubtreeDepth($entity->getId(), $depth);
            foreach ($entity->getChildren() as $child) {
                $this->em->refresh($child);
            }
        }
    }

    /**
     * Places a new entity: at the end of its siblings unless a position is given.
     */
    public function placeNew(AttributeEntity $entity): void
    {
        $this->assertPlaceable($entity);
        $entity->setHierarchyDepth($this->computeDepth($entity));
        if (null !== $entity->getParent() && 0 === $entity->getPosition()) {
            $entity->setPosition($entity->getParent()->getChildren()->count());
        }
    }

    public function assertRemovable(AttributeEntity $entity): void
    {
        if ($this->repository->count(['parent' => $entity->getId()]) > 0) {
            throw new UnprocessableEntityHttpException(sprintf('Entity "%s" has children in the hierarchy: move or remove them first', $entity->getValue()));
        }
    }

    public function computeDepth(AttributeEntity $entity): ?int
    {
        if ($entity->getList()?->getRootEntity() === $entity) {
            return 0;
        }

        $parentDepth = $entity->getParent()?->getHierarchyDepth();

        return null !== $parentDepth ? $parentDepth + 1 : null;
    }

    private function assertPlaceable(AttributeEntity $entity): void
    {
        if (null !== $entity->getParent() && !$entity->getList()?->isHierarchical()) {
            throw new UnprocessableEntityHttpException('The list has no root entity: set one before placing entities');
        }
    }
}
