<?php

namespace Alchemy\TrackBundle\Doctrine;

use Alchemy\TrackBundle\LoggableChangeSetInterface;
use Alchemy\TrackBundle\Model\TrackActionTypeEnum;
use Alchemy\TrackBundle\Service\ChangeLogManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onFlush)]
final class LoggableChangeSetListener
{
    public static bool $disabled = false;

    public function __construct(
        private readonly ChangeLogManager $changeLogManager,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (self::$disabled) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof LoggableChangeSetInterface) {
                $this->changeLogManager->createChangeLog(
                    TrackActionTypeEnum::CREATE,
                    $entity,
                );
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof LoggableChangeSetInterface) {
                $changeSet = $uow->getEntityChangeSet($entity);

                if (!empty($changeSet)) {
                    $this->changeLogManager->createChangeLog(
                        TrackActionTypeEnum::UPDATE,
                        $entity,
                        [],
                        $changeSet
                    );
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof LoggableChangeSetInterface) {
                $this->changeLogManager->createChangeLog(
                    TrackActionTypeEnum::DELETE,
                    $entity
                );
            }
        }
    }
}
