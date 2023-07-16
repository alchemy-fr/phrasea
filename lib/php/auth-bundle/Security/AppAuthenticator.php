<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Security;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\AuthBundle\Http\AuthStateEncoder;
use Alchemy\AuthBundle\Security\Badge\AccessTokenBadge;
use Alchemy\AuthBundle\Security\Token\JwtToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AppAuthenticator extends AccessTokenAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        JwtValidatorInterface $jwtValidator,
        private readonly KeycloakUrlGenerator $keycloakUrlGenerator,
        private readonly string $clientId,
        private readonly AuthStateEncoder $authStateEncoder,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($jwtValidator);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $accessTokenBadge = $passport->getBadge(AccessTokenBadge::class);

        $user = $passport->getUser();
        $token = new JwtToken($accessTokenBadge->getAccessToken(), $user->getRoles());
        $token->setUser($user);

        return $token;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $state = $this->authStateEncoder->encodeState($request->getUri());

        return new RedirectResponse($this->keycloakUrlGenerator->getAuthorizeUrl(
            $this->clientId,
            $this->urlGenerator->generate('alchemy_auth_oauth_check', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $state
        ));
    }
}
