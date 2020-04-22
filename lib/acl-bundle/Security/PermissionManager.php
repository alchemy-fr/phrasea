<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Security;

use Alchemy\AclBundle\AclObjectInterface;
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

        $objectKey = $objectKey.':'.$object->getId();

        $ace = $this->repository->getAce($user->getId(), $objectKey);
        if (null === $ace) {
            return false;
        }

        $mask = $ace->getMask();

        return ($mask & $permission) === $permission;
    }
}
