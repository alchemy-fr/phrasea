<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RemoteUserProvider implements UserProviderInterface
{
    public function __construct(private readonly AuthServiceClient $client)
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

        return new RemoteUser($data['sub'], $data['preferred_username'], $data['realm_access']['roles'] ?? [], $data['groups'] ?? []);
    }
}
