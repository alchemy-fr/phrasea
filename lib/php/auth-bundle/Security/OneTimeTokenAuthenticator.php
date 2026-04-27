<?php

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\RandomUtil;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class OneTimeTokenAuthenticator
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly CacheInterface $oneTimeTokenCache,
    ) {
    }

    public function createToken(): string
    {
        $userOrClient = $this->getStrictUserOrOAuthClient();

        $token = RandomUtil::generateString(128);

        $this->oneTimeTokenCache->get($token, function (ItemInterface $item) use ($userOrClient) {
            $item->expiresAfter(60 * 5);

            if ($userOrClient instanceof JwtUser) {
                return [
                    'type' => 'user',
                    'id' => $userOrClient->getUserIdentifier(),
                    'username' => $userOrClient->getUsername(),
                    'jwt' => $userOrClient->getJwt(),
                    'roles' => $userOrClient->getRoles(),
                    'groups' => $userOrClient->getGroups(),
                    'scopes' => $userOrClient->getScopes(),
                ];
            } elseif ($userOrClient instanceof JwtOauthClient) {
                return [
                    'type' => 'client',
                    'id' => $userOrClient->getUserIdentifier(),
                    'jwt' => $userOrClient->getJwt(),
                    'scopes' => $userOrClient->getScopes(),
                ];
            }
            throw new AuthenticationException();
        });

        return $token;
    }

    public function consumeToken(string $token): JwtInterface
    {
        $user = $this->oneTimeTokenCache->get($token, function (): never {
            throw new AuthenticationException();
        });

        $this->oneTimeTokenCache->delete($token);

        if ('client' === $user['type']) {
            return new JwtOauthClient(
                $user['jwt'],
                $user['id'],
                $user['scopes'],
            );
        }

        return new JwtUser(
            $user['jwt'],
            $user['id'],
            $user['username'],
            $user['roles'],
            $user['groups'],
            $user['scopes'],
        );
    }
}
