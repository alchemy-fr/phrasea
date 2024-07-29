<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Consumer\Handler\Search\AttributeEntityUpdate;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\AttributeEntity;
use App\Entity\Core\File;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onFlush)]
final readonly class AttributeEntityListener implements EventSubscriber
{
    public function __construct(
        private PostFlushStack $postFlushStack,
    )
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entityUpdate) {
            if ($entityUpdate instanceof AttributeEntity) {
                $changeSet = $uow->getEntityChangeSet($entityUpdate);

                if ($changeSet['value'] ?? false) {
                    [$old, $new] = $changeSet['value'];
                    $this->postFlushStack->addBusMessage(new AttributeEntityUpdate(
                        $entityUpdate->getId(),
                        $old,
                        $new,
                    ));
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
