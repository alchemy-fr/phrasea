<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityList;
use App\Entity\Core\EntityType;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectManager;

#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::onFlush)]
final class AttributeEntityReferentialListener implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        if (!$entity instanceof AttributeEntity) {
            return;
        }

        $list = $entity->getList();
        if (null === $list) {
            throw new \LogicException('An attribute entity must belong to an entity list.');
        }

        $entityTypeName = $entity->getEntityType() ?? $list->getName();
        if (null === $entityTypeName) {
            throw new \LogicException('An entity type must have a name.');
        }
        $this->setEntityType($entity, $entityTypeName, $em);

        $root = $this->getRootEntity($entity, $list, $em);
        if ($entity !== $root && null === $entity->getParent()) {
            $entity->setParent($root);
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The referential listener requires Doctrine ORM.');
        }
        $unitOfWork = $em->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof AttributeEntity) {
                $list = $entity->getList();
                $entityTypeName = $entity->getEntityType() ?? $list?->getName();
                if (null === $list || null === $entityTypeName) {
                    throw new \LogicException('An attribute entity must have an entity list and entity type.');
                }

                $this->setEntityType($entity, $entityTypeName, $em);
                $this->recomputeChangeSet($unitOfWork, $em, $entity);
            }
        }
    }

    private function getRootEntity(AttributeEntity $entity, EntityList $list, ObjectManager $em): AttributeEntity
    {
        $name = $list->getName();
        if (null === $name) {
            throw new \LogicException('An entity list must have a name.');
        }

        if (null === $entity->getParent() && $entity->getValue() === $name) {
            return $entity;
        }

        $root = $em->getRepository(AttributeEntity::class)->findOneBy([
            'list' => $list,
            'parent' => null,
        ]);
        if ($root instanceof AttributeEntity) {
            return $root;
        }

        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The referential listener requires Doctrine ORM.');
        }
        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $scheduledEntity) {
            if (
                $scheduledEntity instanceof AttributeEntity
                && $scheduledEntity !== $entity
                && $scheduledEntity->getList() === $list
                && null === $scheduledEntity->getParent()
                && $scheduledEntity->getValue() === $name
            ) {
                return $scheduledEntity;
            }
        }

        $root = new AttributeEntity();
        $root->setList($list);
        $root->setValue($name);
        $this->setEntityType($root, $name, $em);
        $em->persist($root);
        $this->computeChangeSet($em, $root);

        return $root;
    }

    private function setEntityType(AttributeEntity $attributeEntity, string $name, ObjectManager $em): void
    {
        $entityType = $attributeEntity->getEntityTypeEntity();
        if (null !== $entityType && $entityType->getName() === $name) {
            return;
        }

        $entityType = (new EntityType())->setName($name);
        $em->persist($entityType);
        $attributeEntity->setEntityTypeEntity($entityType);
        $this->computeInsertChangeSet($em, $entityType);
    }

    private function recomputeChangeSet(UnitOfWork $unitOfWork, ObjectManager $em, object $entity): void
    {
        $metadata = $em->getClassMetadata($entity::class);
        $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
    }

    private function computeChangeSet(ObjectManager $em, object $entity): void
    {
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The referential listener requires Doctrine ORM.');
        }

        $em->getUnitOfWork()->computeChangeSet($em->getClassMetadata($entity::class), $entity);
    }

    private function computeInsertChangeSet(ObjectManager $em, object $entity): void
    {
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The referential listener requires Doctrine ORM.');
        }

        $unitOfWork = $em->getUnitOfWork();
        if ($unitOfWork->isScheduledForInsert($entity) && [] === $unitOfWork->getEntityChangeSet($entity)) {
            $unitOfWork->computeChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }
}
