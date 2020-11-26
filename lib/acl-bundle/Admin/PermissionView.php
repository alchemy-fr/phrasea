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

    public function getObjectKey(string $entityClass): string
    {
        return $this->objectMapping->getObjectKey($entityClass);
    }

    public function getViewParameters(string $objectKey, ?string $id): array
    {
        $permissions = PermissionInterface::PERMISSIONS;
        $aces = [];
        if (null !== $id) {
            $aces = array_merge($aces, $this->repository->getObjectAces($objectKey, null));
        }
        $aces = array_merge($aces, $this->repository->getObjectAces($objectKey, $id));

        $users = [
            AccessControlEntry::USER_WILDCARD => 'All users',
        ];
        foreach ($this->userRepository->getUsers() as $user) {
            $users[$user['id']] = $user['username'];
        }
        $groups = [];
        foreach ($this->groupRepository->getGroups() as $group) {
            $groups[$group['id']] = $group['name'];
        }

        $aces = array_map(function (AccessControlEntry $ace) use ($users, $groups, $permissions): array {
            $name = $ace->getUserId();
            switch ($ace->getUserType()) {
                case AccessControlEntry::TYPE_USER_VALUE:
                    $name = $ace->getUserId() ? ($users[$ace->getUserId()] ?? $name) : AccessControlEntry::USER_WILDCARD;
                    break;
                case AccessControlEntry::TYPE_GROUP_VALUE:
                    $name = $groups[$ace->getUserId()] ?? $name;
                    break;
            }

            return [
                'userType' => $ace->getUserTypeString(),
                'userId' => $ace->getUserId() ?? AccessControlEntry::USER_WILDCARD,
                'name' => $name,
                'objectId' => $ace->getObjectId(),
                'permissions' => array_map(fn (int $p): bool => $ace->hasPermission($p), $permissions),
            ];
        }, $aces);

        $params = [
            'USER_WILDCARD' => AccessControlEntry::USER_WILDCARD,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
            'groups' => $groups,
            'object_type' => $objectKey,
        ];

        if(null !== $id) {
            $params['object_id'] = $id;
        }

        return $params;
    }
}
