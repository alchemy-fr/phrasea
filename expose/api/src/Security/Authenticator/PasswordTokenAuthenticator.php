<?php

namespace App\Security\Authenticator;

use App\Security\Authentication\PasswordToken;
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

class PasswordTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return $request->headers->has('X-Passwords');
    }

    public function authenticate(Request $request): Passport
    {
        $passwords = $request->headers->get('X-Passwords');
        if (null === $passwords) {
            throw new CustomUserMessageAuthenticationException('Invalid password');
        }

        $passwordBadge = new PasswordBadge($passwords);

        return new SelfValidatingPassport(new UserBadge($passwords), [$passwordBadge]);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $accessTokenBadge = $passport->getBadge(PasswordBadge::class);
        return new PasswordToken($accessTokenBadge->getPasswords());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
