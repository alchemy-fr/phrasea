<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Repository;

use Alchemy\AclBundle\Model\AclUserInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface as AclUserRepositoryInterface;

/**
 * The users as acl-bundle gives them back (ACEs…): reduced to IdentityProjection::USER_FIELDS.
 */
final readonly class AclUserRepository implements AclUserRepositoryInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Full records: only listed by the admin permissions page.
     */
    public function getUsers(array $options = []): array
    {
        return $this->userRepository->getUsers($options);
    }

    public function getUser(string $userId, array $options = []): ?array
    {
        return IdentityProjection::user($this->userRepository->getUser($userId, $options));
    }

    public function getAclGroupsId(AclUserInterface $user): array
    {
        return $this->userRepository->getAclGroupsId($user);
    }
}
