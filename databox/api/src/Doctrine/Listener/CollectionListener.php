<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\OutputTransformer\CollectionOutputTransformer;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Consumer\Handler\Search\IndexCollectionBranchHandler;
use App\Entity\Core\Collection;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsDoctrineListener(Events::postUpdate)]
class CollectionListener implements EventSubscriber
{
    use ChangeFieldListenerTrait;

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

    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
        ];
    }
}
