<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
        throw new Exception('Not implemented');
    }

    public function loadUserFromAccessToken(string $accessToken): ?UserInterface
    {
        try {
            $response = $this->client->request('GET', '/me', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                throw new UnauthorizedHttpException($e->getResponse()->getBody()->getContents());
            }

            throw $e;
        }

        if (401 === $response->getStatusCode()) {
            throw new UnauthorizedHttpException($response->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();
        $data = \GuzzleHttp\json_decode($content, true);
        $user = new RemoteUser($data['user_id'], $data['email'], $data['roles']);

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
