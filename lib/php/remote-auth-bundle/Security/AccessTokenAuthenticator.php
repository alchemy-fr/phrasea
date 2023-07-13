<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Security\Badge\AccessTokenBadge;
use Alchemy\RemoteAuthBundle\Security\Token\JwtToken;
use Alchemy\RemoteAuthBundle\Security\Token\RemoteAuthToken;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AccessTokenAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;
    final public const COOKIE_NAME = 'auth-access-token';

    public function __construct(
        private readonly JwtValidatorInterface $jwtValidator,
    )
    {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = RequestHelper::getAuthorizationFromRequest($request)
            ?? $request->cookies->get(self::COOKIE_NAME);

        if (empty($accessToken)) {
            throw new CustomUserMessageAuthenticationException('Missing access_token');
        }

        try {
            if (!$this->jwtValidator->isTokenValid($accessToken)) {
                throw new CustomUserMessageAuthenticationException('Invalid token.');
            }
        } catch (\InvalidArgumentException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid token.');
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

        $user = $passport->getUser();
        $token = new JwtToken($accessTokenBadge->getAccessToken(), $user->getRoles());
        $token->setUser($user);

        return $token;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
