<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Attribute\AttributeInterface;
use App\Consumer\Handler\Search\AttributeEntityDelete;
use App\Consumer\Handler\Search\AttributeEntityUpdate;
use App\Entity\Core\AttributeEntity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(Events::onFlush)]
final readonly class AttributeEntityListener implements EventSubscriber
{
    public function __construct(
        private PostFlushStack $postFlushStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        foreach ($uow->getScheduledEntityUpdates() as $entityUpdate) {
            if ($entityUpdate instanceof AttributeEntity) {
                $changeSet = $uow->getEntityChangeSet($entityUpdate);
                $locales = [];
                if ($changeSet['value'] ?? false) {
                    $locales[AttributeInterface::NO_LOCALE] = true;
                }
                if ($changeSet['translations'] ?? false) {
                    [$old, $new] = $changeSet['translations'];
                    foreach ($new as $l => $v) {
                        if (isset($old[$l]) && $old[$l] !== $v) {
                            $locales[$l] = true;
                        }
                    }
                }
                if ($changeSet['synonyms'] ?? false) {
                    [$old, $new] = $changeSet['synonyms'];
                    foreach (($new ?? []) as $l => $v) {
                        if (isset($old[$l]) && $old[$l] != $v) {
                            $locales[$l] = true;
                        }
                    }
                    foreach (($old ?? []) as $l => $v) {
                        if (isset($new[$l]) && $new[$l] != $v) {
                            $locales[$l] = true;
                        }
                    }
                }
                if (!empty($locales)) {
                    $this->postFlushStack->addBusMessage(new AttributeEntityUpdate(
                        $entityUpdate->getId(),
                        array_keys($locales),
                    ));
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof AttributeEntity) {
                $this->postFlushStack->addBusMessage(new AttributeEntityDelete(
                    $entity->getId(),
                    $entity->getList()->getId(),
                    $entity->getWorkspaceId(),
                ));
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
