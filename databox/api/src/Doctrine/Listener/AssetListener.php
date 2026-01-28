<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Search\IndexAssetAttributes;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\File;
use App\Model\ActionLogTypeEnum;
use App\Service\Log\ActionLogManager;
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
        private readonly ActionLogManager $actionLogManager,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entityDelete) {
            if ($entityDelete instanceof Asset) {
                if (null !== ($storyCollection = $entityDelete->getStoryCollection())) {
                    $storyCollection->setStoryAsset(null);
                    $em->persist($storyCollection);
                    $em->remove($storyCollection);
                }
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entityUpdate) {
            if ($entityUpdate instanceof Asset && !$entityUpdate->isNoFileVersion()) {
                $changeSet = $uow->getEntityChangeSet($entityUpdate);
                $fileChange = $changeSet['source'] ?? null;
                if ($fileChange) {
                    $this->actionLogManager->createLogAction(
                        ActionLogTypeEnum::AssetSubstituted,
                        $entityUpdate,
                        [
                            'oldSourceId' => $fileChange[0]?->getId(),
                            'newSourceId' => $fileChange[1]?->getId(),
                        ],
                        inOnFlush: true,
                    );

                    if ($fileChange[0] instanceof File) {
                        $assetFileVersion = new AssetFileVersion();
                        $assetFileVersion->setAsset($entityUpdate);
                        $assetFileVersion->setFile($fileChange[0]);

                        $uow->persist($assetFileVersion);

                        $metadata = $em->getClassMetadata($assetFileVersion::class);
                        $uow->computeChangeSet($metadata, $assetFileVersion);
                    }
                }

                $refCollection = $changeSet['referenceCollection'] ?? false;
                if ($refCollection) {
                    $this->actionLogManager->createLogAction(
                        ActionLogTypeEnum::AssetMoved,
                        $entityUpdate,
                        [
                            'oldCollectionId' => $refCollection[0]->getId(),
                            'newCollectionId' => $refCollection[1]->getId(),
                        ],
                        inOnFlush: true,
                    );
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
