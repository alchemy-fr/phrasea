<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Security\Badge\AccessTokenBadge;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class RemoteAccessAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;
    public const COOKIE_NAME = 'auth-access-token';

    private string $routeName;
    private UserCheckerInterface $userChecker;

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = RequestHelper::getAuthorizationFromRequest($request)
            ?? $request->cookies->get(self::COOKIE_NAME);

        if (empty($accessToken) || 0 !== strpos($accessToken, RemoteAuthToken::TOKEN_PREFIX)) {
            throw new CustomUserMessageAuthenticationException('Invalid access_token');
        }

        $accessTokenBadge = new AccessTokenBadge($accessToken);

        return new SelfValidatingPassport(new UserBadge($accessToken), [$accessTokenBadge]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $accessTokenBadge = $passport->getBadge(AccessTokenBadge::class);
        return new RemoteAuthToken($accessTokenBadge->getAccessToken(), $accessTokenBadge->getRoles());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
