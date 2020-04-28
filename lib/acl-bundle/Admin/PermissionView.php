<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Admin;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;

class PermissionView
{
    private ObjectMapping $objectMapping;
    private PermissionRepositoryInterface $repository;
    private UserRepositoryInterface $userRepository;
    private GroupRepositoryInterface $groupRepository;

    public function __construct(
        ObjectMapping $objectMapping,
        PermissionRepositoryInterface $repository,
        UserRepositoryInterface $userRepository,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->objectMapping = $objectMapping;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
    }

    public function getAclViewParameters(string $entityClass, string $id): array
    {
        $objectKey = $this->objectMapping->getObjectKey($entityClass);
        $permissions = PermissionInterface::PERMISSIONS;
        $aces = $this->repository->getObjectAces($objectKey, $id);

        $users = [];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        $aces = array_map(function (AccessControlEntry $ace) use ($users, $groups, $permissions): array {
            $name = $ace->getEntityId();
            switch ($ace->getEntityType()) {
                case AccessControlEntry::ENTITY_USER:
                    $name = $users[$ace->getEntityId()] ?? $name;
                    break;
                case AccessControlEntry::ENTITY_GROUP:
                    $name = $groups[$ace->getEntityId()] ?? $name;
                    break;
            }

            return [
                'entityType' => $ace->getEntityTypeString(),
                'entityId' => $ace->getEntityId(),
                'name' => $name,
                'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
            ];
        }, $aces);

        return [
            'object_type' => $objectKey,
            'object_id' => $id,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
            'groups' => $groups,
        ];
    }

}
