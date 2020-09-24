<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Exception;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RemoteUserProvider implements UserProviderInterface
{
    /**
     * @var AuthServiceClient
     */
    private $client;

    public function __construct(AuthServiceClient $client)
    {
        $this->client = $client;
    }

    public function loadUserByUsername($username)
    {
        throw new Exception('Not implemented');
    }

    /**
     * @return RemoteUser
     */
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

    public function refreshUser(UserInterface $user)
    {
        return $user;
    }

    public function supportsClass($class)
    {
        return RemoteUser::class === $class;
    }
}
