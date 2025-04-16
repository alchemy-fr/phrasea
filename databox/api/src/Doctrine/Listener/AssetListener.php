<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Search\IndexAssetAttributes;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\File;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onFlush)]
#[AsDoctrineListener(Events::postUpdate)]
class AssetListener
{
    use ChangeFieldListenerTrait;

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

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

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Asset) {
            return;
        }

        if (!$this->hasChangedField([
            'privacy',
            'owner',
            'referenceCollection',
        ], $args->getObjectManager(), $entity)) {
            return;
        }

        $this->postFlushStack->addBusMessage(new IndexAssetAttributes($entity->getId()));
    }
}
