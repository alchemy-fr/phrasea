<?php

declare(strict_types=1);

namespace App\Security\Authenticator;

use Alchemy\RemoteAuthBundle\Security\RequestHelper;
use App\Security\AssetTokenUser;
use App\Security\Authentication\AssetToken;
use App\Security\Badge\AssetTokenBadge;
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

class AssetTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'AssetToken ');
    }

    public function authenticate(Request $request): Passport
    {
        $assetToken = RequestHelper::getAuthorizationFromRequest($request, 'AssetToken', false);

        if (null === $assetToken) {
            throw new CustomUserMessageAuthenticationException('Invalid AssetToken');
        }

        $assetTokenBadge = new AssetTokenBadge($assetToken);

        return new SelfValidatingPassport(new UserBadge($assetToken, function () {
            return new AssetTokenUser();
        }), [$assetTokenBadge]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $assetTokenBadge = $passport->getBadge(AssetTokenBadge::class);

        $assetToken = new AssetToken($assetTokenBadge->getAccessToken());
        $assetToken->setUser($passport->getUser());

        return $assetToken;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
