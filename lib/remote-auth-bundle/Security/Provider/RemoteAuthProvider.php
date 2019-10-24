<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security\Provider;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Alchemy\RemoteAuthBundle\Security\Client\RemoteClient;
use Alchemy\RemoteAuthBundle\Security\InvalidResponseException;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class RemoteAuthProvider implements AuthenticationProviderInterface
{
    /**
     * @var RemoteClient
     */
    private $client;

    /**
     * @required
     */
    public function setClient(RemoteClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param RemoteAuthToken $token
     */
    public function authenticate(TokenInterface $token)
    {
        try {
            $tokenInfo = $this->getTokenInfo($token->getAccessToken());
        } catch (AuthenticationException $e) {
            throw new AuthenticationException('The Remote token authentication failed.', 0, $e);
        }

        $roles = [];
        $user = $this->getUserFromToken($tokenInfo);
        if ($user) {
            $roles = $user->getRoles();
        }

        $authenticatedToken = new RemoteAuthToken($token->getAccessToken(), $roles);
        $authenticatedToken->setScopes($tokenInfo['scopes']);
        $authenticatedToken->setAuthenticated(true);
        if ($user instanceof RemoteUser) {
            $authenticatedToken->setUser($user);
        }

        return $authenticatedToken;
    }

    public function getUserFromToken(array $tokenInfo): ?RemoteUser
    {
        if (isset($tokenInfo['user'])) {
            $userData = $tokenInfo['user'];

            return new RemoteUser($userData['id'], $userData['email'], $userData['roles']);
        }

        return null;
    }

    public function getTokenInfo(string $accessToken): array
    {
        try {
            return $this->client->getTokenInfo($accessToken);
        } catch (InvalidResponseException $e) {
            throw new AuthenticationException($e->getMessage());
        }
    }

    public function supports(TokenInterface $token)
    {
        return $token instanceof RemoteAuthToken;
    }
}
