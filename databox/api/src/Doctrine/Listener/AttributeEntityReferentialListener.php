<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Collection;
use App\Entity\Core\EntityList;
use App\Entity\Core\EntityType;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
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

        if ($entity instanceof EntityList) {
            if (null === $entity->getReferentialBranch()) {
                $branch = $this->createBranch(
                    $entity->getName() ?? '',
                    $entity->getWorkspace(),
                    null,
                    $entity->getOwnerId(),
                );
                $entity->setReferentialBranch($branch);
                $em->persist($branch);
                $this->computeChangeSet($em, $branch);
            }

            return;
        }

        if (!$entity instanceof AttributeEntity) {
            return;
        }

        $list = $entity->getList();
        if (null === $list) {
            throw new \LogicException('An attribute entity must belong to an entity list.');
        }

        $entityTypeName = $entity->getEntityType();
        if (null !== $entityTypeName) {
            $this->setEntityType($entity, $entityTypeName, $em);
        }

        $parent = $list->getReferentialBranch();
        if (null === $parent) {
            throw new \LogicException('An entity list must have a referential branch.');
        }

        if (null === $entity->getReferentialBranch()) {
            $branch = $this->createBranch(
                $entity->getValue() ?? '',
                $list->getWorkspace(),
                $parent,
                $list->getOwnerId(),
            );
            $entity->setReferentialBranch($branch);
            $em->persist($branch);
            $this->computeChangeSet($em, $branch);
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
            if ($entity instanceof EntityList && null !== $entity->getReferentialBranch()) {
                $branch = $entity->getReferentialBranch();
                $workspace = $entity->getWorkspace();
                if (null === $workspace) {
                    throw new \LogicException('An entity list must belong to a workspace.');
                }
                $branch->setName($entity->getName());
                $branch->setWorkspace($workspace);
                $branch->setOwnerId($entity->getOwnerId() ?? $workspace->getOwnerId());
                $this->recomputeChangeSet($unitOfWork, $em, $branch);
            } elseif ($entity instanceof AttributeEntity) {
                $this->updateAttributeEntity($entity, $unitOfWork, $em);
            }
        }
    }

    private function updateAttributeEntity(AttributeEntity $entity, UnitOfWork $unitOfWork, EntityManagerInterface $em): void
    {
        $list = $entity->getList();
        if (null === $list) {
            throw new \LogicException('An attribute entity must have an entity list.');
        }

        $entityTypeName = $entity->getEntityType();
        if (null !== $entityTypeName) {
            $this->setEntityType($entity, $entityTypeName, $em);
        }

        $branch = $entity->getReferentialBranch();
        if (null === $branch) {
            throw new \LogicException('An attribute entity must have a referential branch.');
        }
        $parent = $list->getReferentialBranch();
        if (null === $parent) {
            throw new \LogicException('An entity list must have a referential branch.');
        }
        $workspace = $list->getWorkspace();
        if (null === $workspace) {
            throw new \LogicException('An entity list must belong to a workspace.');
        }

        $branch->setName(null === $entity->getValue() ? null : mb_substr($entity->getValue(), 0, 255));
        $branch->setWorkspace($workspace);
        $branch->setOwnerId($list->getOwnerId() ?? $workspace->getOwnerId());
        $branch->setParent($parent);

        $this->recomputeChangeSet($unitOfWork, $em, $branch);
        $this->recomputeChangeSet($unitOfWork, $em, $entity);
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

    private function createBranch(
        string $name,
        ?Workspace $workspace,
        ?Collection $parent,
        ?string $ownerId,
    ): Collection {
        if (null === $workspace) {
            throw new \LogicException('A referential branch must belong to a workspace.');
        }

        $branch = new Collection();
        $branch->setName(mb_substr($name, 0, 255));
        $branch->setWorkspace($workspace);
        $branch->setOwnerId($ownerId ?? $workspace->getOwnerId());
        $branch->setLocale($workspace->getEnabledLocales()[0] ?? 'en');
        $branch->setPrivacy(WorkspaceItemPrivacyInterface::SECRET);
        $branch->setAutoSubscribeOwner(false);
        $branch->setParent($parent);

        return $branch;
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
