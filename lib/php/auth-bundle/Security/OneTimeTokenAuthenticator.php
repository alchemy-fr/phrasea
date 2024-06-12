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
        $user = $this->getStrictUser();

        $token = RandomUtil::generateString(128);

        $this->oneTimeTokenCache->get($token, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(60 * 5);

            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'jwt' => $user->getJwt(),
                'roles' => $user->getRoles(),
                'groups' => $user->getGroups(),
                'scopes' => $user->getScopes(),
            ];
        });

        return $token;
    }

    public function consumeToken(string $token): JwtUser
    {
        $user = $this->oneTimeTokenCache->get($token, function (): never {
            throw new AuthenticationException();
        });

        $this->oneTimeTokenCache->delete($token);

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
