<?php

declare(strict_types=1);

namespace App\Listener;

use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\ESBundle\Indexer\Operation;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use App\Api\OutputTransformer\CollectionOutputTransformer;
use App\Consumer\Handler\Search\AclAddUserToCollectionAssets;
use App\Consumer\Handler\Search\AclAddUserToWorkspaceAssets;
use App\Consumer\Handler\Search\IndexAllAssets;
use App\Consumer\Handler\Search\IndexAllCollections;
use App\Consumer\Handler\Search\IndexCollectionBranch;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsEventListener(event: AclUpsertEvent::NAME, method: 'onAclUpsert')]
#[AsEventListener(event: AclDeleteEvent::NAME, method: 'onAclDelete')]
readonly class AclListener
{
    public function __construct(
        private SearchIndexer $searchIndexer,
        private ObjectMapping $objectMapping,
        private MessageBusInterface $bus,
        private TagAwareCacheInterface $collectionCache,
    ) {
    }

    private function hasPermission(int $permissions, int $permissionToCheck): bool
    {
        return ($permissions & $permissionToCheck) === $permissionToCheck;
    }

    private function hasOneOfPermissions(int $permissions, array $permissionsToCheck): bool
    {
        foreach ($permissionsToCheck as $permission) {
            if ($this->hasPermission($permissions, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function onAclUpsert(AclUpsertEvent $event): void
    {
        $this->handleChange(
            $event,
            $event->getPermissions(),
            $event->getPreviousPermissions() ?? 0
        );
    }

    public function onAclDelete(AclDeleteEvent $event): void
    {
        if (null === $event->getPreviousPermissions()) {
            throw new \LogicException('Previous permissions must be set for ACL delete events');
        }

        $this->handleChange(
            $event,
            0,
            $event->getPreviousPermissions()
        );
    }

    private function handleChange(AclEvent $event, int $newPermissions, int $previousPermissions): void
    {
        $objectClass = $this->objectMapping->getClassName($event->getObjectType());
        $assetsHandled = false;

        switch ($objectClass) {
            case Workspace::class:
                $discriminantPerms = [
                    PermissionInterface::CHILD_VIEW,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ];

                if (!$this->hasOneOfPermissions($previousPermissions, $discriminantPerms)) {
                    if ($this->hasOneOfPermissions($newPermissions, $discriminantPerms)) {
                        $this->bus->dispatch(new AclAddUserToWorkspaceAssets($event->getObjectId(), $event->getUserType(), $event->getUserId()));
                    }

                    $assetsHandled = true;
                }
                // no break
            case Collection::class:
                $discriminantPerms = [
                    PermissionInterface::VIEW,
                    PermissionInterface::CHILD_VIEW,
                    PermissionInterface::CHILD_OWNER,
                    PermissionInterface::OWNER,
                ];

                if (!$this->hasOneOfPermissions($previousPermissions, $discriminantPerms)) {
                    if ($this->hasOneOfPermissions($newPermissions, $discriminantPerms)) {
                        $collectionId = $event->getObjectId();
                        if (null === $collectionId) {
                            $this->bus->dispatch(new AclAddUserToWorkspaceAssets(null, $event->getUserType(), $event->getUserId()));
                        } else {
                            $this->bus->dispatch(new AclAddUserToCollectionAssets($collectionId, $event->getUserType(), $event->getUserId()));
                        }
                    }

                    $assetsHandled = true;
                }
        }

        $this->indexObject($event->getObjectType(), $event->getObjectId(), $assetsHandled);
    }

    private function indexObject(string $objectType, ?string $objectId, bool $assetsHandled): void
    {
        $this->collectionCache->invalidateTags([CollectionOutputTransformer::COLLECTION_CACHE_NS]);

        $objectClass = $this->objectMapping->getClassName($objectType);

        if (null === $objectId) {
            switch ($objectClass) {
                case Workspace::class:
                case Asset::class:
                    if (!$assetsHandled) {
                        $this->bus->dispatch(new IndexAllAssets());
                    }
                    break;
                case Collection::class:
                    $this->bus->dispatch(new IndexAllCollections());
                    if (!$assetsHandled) {
                        $this->bus->dispatch(new IndexAllAssets());
                    }
                    break;
            }

            return;
        }

        switch ($objectClass) {
            case Workspace::class:
                if (!$assetsHandled) {
                    $this->bus->dispatch(new IndexAllAssets($objectId));
                }
                $this->bus->dispatch(new IndexAllCollections($objectId));
                break;
            case Asset::class:
                $this->searchIndexer->scheduleObjectsIndex($objectClass, [$objectId], Operation::Upsert);
                break;
            case Collection::class:
                $this->bus->dispatch(new IndexCollectionBranch($objectId, !$assetsHandled));
                break;
        }
    }
}
