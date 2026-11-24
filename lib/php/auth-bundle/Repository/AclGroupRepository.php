<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Repository\GroupRepositoryInterface as AclGroupRepositoryInterface;

/**
 * The groups as acl-bundle gives them back (ACEs…): reduced to IdentityProjection::GROUP_FIELDS.
 */
final readonly class AclGroupRepository implements AclGroupRepositoryInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * Full records: only listed by the admin permissions page.
     */
    public function getGroups(array $options = []): array
    {
        return $this->groupRepository->getGroups($options);
    }

    public function getGroup(string $groupId, array $options = []): ?array
    {
        return IdentityProjection::group($this->groupRepository->getGroup($groupId, $options));
    }
}
