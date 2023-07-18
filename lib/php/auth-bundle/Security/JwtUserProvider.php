<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\OAuthClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

readonly class JwtUserProvider implements UserProviderInterface
{
    public function __construct(
        private JwtExtractor $jwtExtractor,
        private JwtValidatorInterface $jwtValidator,
        private OAuthClient $oauthClient,
    )
    {
    }

    /**
     * @param JwtUser $user
     *
     * @return JwtUser
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        $jwt = $user->getJwt();
        $token = $this->jwtExtractor->parseJwt($jwt);

        if ($token->isExpired(new \DateTimeImmutable())) {
            if (null !== $refreshToken = $user->getRefreshToken()) {
                try {
                    [$accessToken, $refreshToken] = $this->getRefreshedToken($refreshToken);
                } catch (ClientException) {
                    throw new AuthenticationServiceException('Cannot refresh token');
                }

                /** @var JwtUser $refreshedUser */
                $refreshedUser = $this->loadUserByIdentifier($accessToken);
                $refreshedUser->setRefreshToken($refreshToken);

                return $refreshedUser;
            }
        }

        if (!$this->jwtValidator->isTokenValid($token)) {
            throw new AuthenticationServiceException('Expired or invalid JWT');
        }

        return $user;
    }

    private function getRefreshedToken(string $refreshToken): array
    {
        return $this->oauthClient->getTokenFromRefreshToken($refreshToken);
    }

    public function supportsClass($class): bool
    {
        return JwtUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $token = $this->jwtExtractor->parseJwt($identifier);

        return $this->jwtExtractor->getUserFromToken($token);
    }
}
