<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Alchemy\RemoteAuthBundle\Security\RemoteAuthAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginController extends AbstractAdminController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(OAuthClient $OAuthClient, Request $request): Response
    {
        $redirectUri = $this->getRedirectUrl($request->get('r'));

        return $this->redirect($OAuthClient->getAuthorizeUrl($redirectUri));
    }

    /**
     * @Route("/auth/check", name="auth_check")
     */
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteAuthProvider $userProvider,
        RemoteAuthAuthenticator $authenticator
    ): Response {
        $accessToken = $oauthClient->getAccessTokenFromAuthorizationCode(
            $request->get('code'),
            $this->getRedirectUrl()
        );

        $tokenInfo = $userProvider->getTokenInfo($accessToken);
        $user = $userProvider->getUserFromToken($tokenInfo);

        $authenticator->authenticateUser($request, $accessToken, $tokenInfo, $user, 'admin');

        return $this->redirectToRoute('easyadmin');
    }

    private function getRedirectUrl(?string $redirectUri = null): string
    {
        $parameters = [];
        if (!empty($redirectUri)) {
            $parameters['r'] = $redirectUri;
        }

        return $this->generateUrl('alchemy_admin_auth_check', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
