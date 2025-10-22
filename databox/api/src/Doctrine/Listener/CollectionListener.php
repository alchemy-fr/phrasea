<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\OutputTransformer\CollectionOutputTransformer;
use App\Consumer\Handler\Collection\DeleteCollection;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Entity\Core\Collection;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsDoctrineListener(Events::postUpdate)]
#[AsDoctrineListener(Events::onFlush)]
class CollectionListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

    public bool $softDeleteEnabled = true;

    public function __construct(
        private readonly PostFlushStack $postFlushStack,
        private readonly TagAwareCacheInterface $collectionCache,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Collection) {
            return;
        }

        if (!$this->hasChangedField([
            'privacy',
            'owner',
            'parent',
        ], $args->getObjectManager(), $entity)) {
            return;
        }

        $this->collectionCache->invalidateTags([CollectionOutputTransformer::COLLECTION_CACHE_NS]);

        $this->postFlushStack->addBusMessage(new IndexCollectionBranch($entity->getId()));
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->softDeleteEnabled) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Collection) {
                // Cancel direct deletion
                if (null === $entity->getDeletedAt()) {
                    $entity->setDeletedAt(new \DateTimeImmutable());
                }
                $uow->persist($entity);
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Collection::class), $entity);
                $this->postFlushStack->addBusMessage(new DeleteCollection($entity->getId()));
            }
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
        ];
    }
}
