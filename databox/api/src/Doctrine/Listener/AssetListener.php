<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class AssetListener implements EventSubscriber
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entityUpdate) {
            if ($entityUpdate instanceof Asset) {
                $changeSet = $uow->getEntityChangeSet($entityUpdate);
                $fileChange = $changeSet['source'] ?? null;
                if ($fileChange) {
                    $assetFileVersion = new AssetFileVersion();
                    $assetFileVersion->setAsset($entityUpdate);
                    $assetFileVersion->setFile($fileChange[0]);

                    $uow->persist($assetFileVersion);

                    $metadata = $em->getClassMetadata(get_class($assetFileVersion));
                    $uow->computeChangeSet($metadata, $assetFileVersion);
                }
            }
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onFlush,
        ];
    }
}
