<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RemoteUserProvider implements UserProviderInterface
{
    public function __construct(private readonly AuthServiceClient $client)
    {
    }

    public function loadUserByUsername($username): never
    {
        throw new \Exception('Not implemented');
    }

    public function loadUserFromAccessToken(string $accessToken): ?UserInterface
    {
        try {
            $data = $this->client->request('GET', '/me', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        } catch (InvalidResponseException $e) {
            throw new UnauthorizedHttpException($e->getMessage());
        }

        $user = new RemoteUser($data['user_id'], $data['username'], $data['roles']);

        return $user;
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
        throw new \Exception('Not implemented');
    }
}
