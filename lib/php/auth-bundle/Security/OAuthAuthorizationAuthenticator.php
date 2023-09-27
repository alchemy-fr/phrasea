<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Client\KeycloakClient;
use Alchemy\AuthBundle\Http\AuthStateEncoder;
use Alchemy\AuthBundle\Security\Badge\AccessTokenBadge;
use Alchemy\AuthBundle\Security\Badge\RefreshTokenBadge;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OAuthAuthorizationAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const CHECK_ROUTE = 'alchemy_auth_oauth_check';

    public function __construct(
        private readonly KeycloakClient $oauthClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
        private readonly AuthStateEncoder $authStateEncoder,
        private readonly string $clientId,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->query->has('code')
            && self::CHECK_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $code = $request->query->get('code');

        if (empty($code)) {
            throw new CustomUserMessageAuthenticationException('Missing access_token');
        }

        try {
            [$accessToken, $refreshToken] = $this->oauthClient->getTokenFromAuthorizationCode($code, $this->getRedirectUri());
        } catch (InvalidArgumentException) {
            throw new CustomUserMessageAuthenticationException('Invalid authorization code.');
        }

        $accessTokenBadge = new AccessTokenBadge($accessToken);
        $refreshTokenBadge = new RefreshTokenBadge($refreshToken);

        return new SelfValidatingPassport(new UserBadge($accessToken), [
            $accessTokenBadge,
            $refreshTokenBadge,
        ]);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $refreshTokenBadge = $passport->getBadge(RefreshTokenBadge::class);
        /** @var JwtUser $user */
        $user = $passport->getUser();
        $user->setRefreshToken($refreshTokenBadge->getRefreshToken());

        return new PostAuthenticationToken($user, $firewallName, $user->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $state = $request->query->get('state');
        if ($state) {
            [
                'redirect' => $redirect,
            ] = $this->authStateEncoder->decodeState($state);

            return new RedirectResponse($redirect);
        }

        if (self::CHECK_ROUTE === $request->attributes->get('_route')) {
            return new RedirectResponse('/');
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $state = $this->authStateEncoder->encodeState($request->getUri());

        return new RedirectResponse($this->keycloakUrlGenerator->getAuthorizeUrl(
            $this->clientId,
            $this->getRedirectUri(),
            $state
        ));
    }

    private function getRedirectUri(): string
    {
        return $this->urlGenerator->generate(self::CHECK_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
