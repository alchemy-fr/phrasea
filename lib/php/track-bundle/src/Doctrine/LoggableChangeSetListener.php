<?php

namespace Alchemy\TrackBundle\Doctrine;

use Alchemy\TrackBundle\Entity\ChangeLog;
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

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof LoggableChangeSetInterface) {
                $changeSet = $uow->getEntityChangeSet($entity);

                if (!empty($changeSet)) {
                    $log = $this->changeLogManager->createChangeLog(
                        TrackActionTypeEnum::UPDATE,
                        $entity,
                        [],
                        $changeSet
                    );

                    $em->persist($log);
                    $uow->computeChangeSet($em->getClassMetadata(ChangeLog::class), $log);
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof LoggableChangeSetInterface) {
                $log = $this->changeLogManager->createChangeLog(
                    TrackActionTypeEnum::DELETE,
                    $entity
                );

                $em->persist($log);
                $uow->computeChangeSet($em->getClassMetadata(ChangeLog::class), $log);
            }
        }
    }
}
