<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Event\AclDeleteEvent;
use Alchemy\AclBundle\Event\AclUpsertEvent;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\UserInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PermissionManager
{
    private ObjectMapping $objectMapper;
    private PermissionRepositoryInterface $repository;
    private EventDispatcherInterface $eventDispatcher;
    private array $cache = [];

    public function __construct(
        ObjectMapping $objectMapper,
        PermissionRepositoryInterface $repository,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->objectMapper = $objectMapper;
        $this->repository = $repository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param UserInterface|RemoteUser $user
     */
    public function isGranted($user, AclObjectInterface $object, int $permission): bool
    {
        if ($object->getAclOwnerId() === $user->getId()) {
            return true;
        }

        $aces = $this->getAces($user, $object);

        foreach ($aces as $ace) {
            if (null !== $ace && ($ace->getMask() & $permission) === $permission) {
                return true;
            }
        }

        return false;
    }

    private function getAces($user, AclObjectInterface $object): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);
        $key = $this->getCacheKey($user->getId(), $objectKey, $object->getId());
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        /** @var AccessControlEntry[] $aces */
        $aces = $this->repository->getAces(
            $user->getId(),
            $user->getGroupIds(),
            $objectKey,
            $object->getId()
        );

        $this->cache[$key] = $aces;

        return $aces;
    }

    public function getAllowedUsers(AclObjectInterface $object, int $permission): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        return $this->repository->getAllowedUserIds(
            $objectKey,
            $object->getId(),
            $permission
        );
    }

    public function getAllowedGroups(AclObjectInterface $object, int $permission): array
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        return $this->repository->getAllowedGroupIds(
            $objectKey,
            $object->getId(),
            $permission
        );
    }

    public function grantUserOnObject(string $userId, AclObjectInterface $object, int $permissions): void
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        $this->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_USER,
            $userId,
            $objectKey,
            $object->getId(),
            $permissions
        );
    }

    public function grantGroupOnObject(string $userId, AclObjectInterface $object, int $permissions): void
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        $this->updateOrCreateAce(
            AccessControlEntryInterface::TYPE_GROUP,
            $userId,
            $objectKey,
            $object->getId(),
            $permissions
        );
    }

    public function updateOrCreateAce(string $userType, string $userId, string $objectType, ?string $objectId, int $permissions): ?AccessControlEntryInterface
    {
        $ace = $this->repository->updateOrCreateAce(
            $userType,
            $userId,
            $objectType,
            $objectId,
            $permissions
        );

        unset($this->cache[$this->getCacheKey($userId, $objectType, $objectId)]);

        $this->eventDispatcher->dispatch(new AclUpsertEvent($objectType, $objectId), AclUpsertEvent::NAME);

        return $ace;
    }

    private function getCacheKey(string $userId, string $objectType, ?string $objectId): string
    {
        return sprintf('%s:%s:%s', $userId, $objectType, $objectId);
    }

    public function deleteAce(string $userType, string $userId, string $objectType, ?string $objectId): void
    {
        if ($this->repository->deleteAce(
            $userType,
            $userId,
            $objectType,
            $objectId
        )) {
            $this->eventDispatcher->dispatch(new AclDeleteEvent($objectType, $objectId), AclDeleteEvent::NAME);
        }

        unset($this->cache[$this->getCacheKey($userId, $objectType, $objectId)]);
    }
}
