<?php

declare(strict_types=1);

namespace App\Security;

use App\Model\User;
use GuzzleHttp\Client;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RemoteUserProvider implements UserProviderInterface
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function loadUserByUsername($username)
    {
        throw new \Exception('Not implemented');
    }

    public function loadUserFromAccessToken(string $accessToken): ?UserInterface
    {
        $response = $this->client->request('GET', '/me', [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);

        if (401 === $response->getStatusCode()) {
            throw new UnauthorizedHttpException($response->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();
        $data = \GuzzleHttp\json_decode($content, true);
        $user = new User($data['user_id'], $data['email'], $data['roles']);

        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        return $user;
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
