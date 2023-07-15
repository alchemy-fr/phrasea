<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\OAuthClient;
use Alchemy\AuthBundle\Model\RemoteUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

readonly class RemoteUserProvider implements UserProviderInterface
{
    public function __construct(
        private OAuthClient $client,
        private RoleMapper $roleMapper,
    )
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass($class): bool
    {
        return RemoteUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $data = $this->client->getTokenInfo($identifier);
        $roles = $this->roleMapper->getRoles($data['roles'] ?? []);

        return new RemoteUser($data['sub'], $data['preferred_username'], $roles, $data['groups'] ?? []);
    }
}
