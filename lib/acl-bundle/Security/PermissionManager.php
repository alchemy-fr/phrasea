<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\UserInterface;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;

class PermissionManager
{
    private ObjectMapping $objectMapper;
    private PermissionRepositoryInterface $repository;

    public function __construct(ObjectMapping $objectMapper, PermissionRepositoryInterface $repository)
    {
        $this->objectMapper = $objectMapper;
        $this->repository = $repository;
    }

    /**
     * @param UserInterface|RemoteUser $user
     */
    public function isGranted($user, AclObjectInterface $object, int $permission): bool
    {
        $objectKey = $this->objectMapper->getObjectKey($object);

        $ace = $this->repository->getAce(
            AccessControlEntry::getEntityTypeFromCode(AccessControlEntry::ENTITY_USER),
            $user->getId(),
            $objectKey,
            $object->getId()
        );
        if (null !== $ace && ($ace->getMask() & $permission) === $permission) {
            return true;
        }

        foreach ($user->getGroupIds() as $groupId) {
            $ace = $this->repository->getAce(
                AccessControlEntry::getEntityTypeFromCode(AccessControlEntry::ENTITY_GROUP),
                $groupId,
                $objectKey,
                $object->getId()
            );
            if (null !== $ace && ($ace->getMask() & $permission) === $permission) {
                return true;
            }
        }

        return false;
    }
}
