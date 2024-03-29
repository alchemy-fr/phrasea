<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\File;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onFlush)]
class AssetListener implements EventSubscriber
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entityUpdate) {
            if ($entityUpdate instanceof Asset && !$entityUpdate->isNoFileVersion()) {
                $changeSet = $uow->getEntityChangeSet($entityUpdate);
                $fileChange = $changeSet['source'] ?? null;
                if ($fileChange && $fileChange[0] instanceof File) {
                    $assetFileVersion = new AssetFileVersion();
                    $assetFileVersion->setAsset($entityUpdate);
                    $assetFileVersion->setFile($fileChange[0]);

                    $uow->persist($assetFileVersion);

                    $metadata = $em->getClassMetadata($assetFileVersion::class);
                    $uow->computeChangeSet($metadata, $assetFileVersion);
                }
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }
}
