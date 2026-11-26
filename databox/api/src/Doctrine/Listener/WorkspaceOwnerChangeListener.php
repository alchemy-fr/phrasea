<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use Alchemy\MessengerBundle\Listener\PostFlushStack;
use App\Api\Mapper\Output\CollectionOutputMapper;
use App\Consumer\Handler\Search\ComputeWorkspaceCollections;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\Handler\Search\IndexAllCollections;
use App\Consumer\MessageDeduplication;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * The workspace owner is denormalized in the access rows of the root collections
 * and in every collection and asset document of the workspace.
 */
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Workspace::class)]
final readonly class WorkspaceOwnerChangeListener
{
    use ChangeFieldListenerTrait;

    public function __construct(
        private PostFlushStack $postFlushStack,
        private TagAwareCacheInterface $collectionCache,
    ) {
    }

    public function postUpdate(Workspace $workspace, PostUpdateEventArgs $args): void
    {
        if (!$this->hasChangedField(['ownerId'], $args->getObjectManager(), $workspace)) {
            return;
        }

        $this->collectionCache->invalidateTags([CollectionOutputMapper::COLLECTION_CACHE_NS]);

        $workspaceId = $workspace->getId();
        $this->postFlushStack->addBusMessage(MessageDeduplication::coalesce(new ComputeWorkspaceCollections($workspaceId)));
        $this->postFlushStack->addBusMessage(IndexAllCollections::dispatchable($workspaceId));
        $this->postFlushStack->addBusMessage(IndexAllAssets::dispatchable($workspaceId));
    }
}
