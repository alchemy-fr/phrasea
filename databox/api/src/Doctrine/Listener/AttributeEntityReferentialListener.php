<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\EntityType;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
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
        if (!$entity instanceof AttributeEntity) {
            return;
        }

        if (null === $entity->getList()) {
            throw new \LogicException('An attribute entity must belong to an entity list.');
        }

        $entityTypeName = $entity->getEntityType();
        if (null !== $entityTypeName) {
            $this->setEntityType($entity, $entityTypeName, $args->getObjectManager());
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The entity type listener requires Doctrine ORM.');
        }

        $unitOfWork = $em->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof AttributeEntity) {
                continue;
            }

            $entityTypeName = $entity->getEntityType();
            if (null !== $entityTypeName) {
                $this->setEntityType($entity, $entityTypeName, $em);
            }

            $metadata = $em->getClassMetadata($entity::class);
            $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
        }
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

        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('The entity type listener requires Doctrine ORM.');
        }

        $unitOfWork = $em->getUnitOfWork();
        if ($unitOfWork->isScheduledForInsert($entityType) && [] === $unitOfWork->getEntityChangeSet($entityType)) {
            $unitOfWork->computeChangeSet($em->getClassMetadata($entityType::class), $entityType);
        }
    }
}
