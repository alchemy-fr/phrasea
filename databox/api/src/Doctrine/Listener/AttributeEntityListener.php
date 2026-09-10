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
final class AttributeEntityListener implements EventSubscriber
{
    public bool $disabled = false;

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->disabled) {
            return;
        }

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
                    $locales += $this->getChangedLocales($old, $new, strict: true);
                }
                if ($changeSet['synonyms'] ?? false) {
                    [$old, $new] = $changeSet['synonyms'];
                    $locales += $this->getChangedLocales($old, $new, strict: false);
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

    /**
     * Locales whose entry differs between the two maps, added and removed ones included.
     *
     * @return array<string, true>
     */
    private function getChangedLocales(?array $old, ?array $new, bool $strict): array
    {
        $locales = [];
        foreach (array_unique([...array_keys($old ?? []), ...array_keys($new ?? [])]) as $locale) {
            $changed = $strict
                ? ($old[$locale] ?? null) !== ($new[$locale] ?? null)
                : ($old[$locale] ?? null) != ($new[$locale] ?? null);
            if ($changed) {
                $locales[$locale] = true;
            }
        }

        return $locales;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }
}
