<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Alchemy\RemoteAuthBundle\Http\AuthStateEncoder;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Alchemy\RemoteAuthBundle\Security\RemoteAuthAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginController extends AbstractAdminController
{
    use TargetPathTrait;

    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, OAuthClient $OAuthClient, AuthStateEncoder $authStateEncoder): Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), 'admin');

        $target = $request->get('r', $targetPath);

        return $this->redirect($OAuthClient->getAuthorizeUrl(
            $this->getRedirectUrl(),
            $target ? $authStateEncoder->encodeState($target) : null
        ));
    }

    /**
     * @Route("/auth/check", name="auth_check")
     */
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteAuthProvider $userProvider,
        RemoteAuthAuthenticator $authenticator,
        AuthStateEncoder $authStateEncoder
    ): Response {
        $accessToken = $oauthClient->getAccessTokenFromAuthorizationCode(
            $request->get('code'),
            $this->getRedirectUrl()
        );

        $tokenInfo = $userProvider->getTokenInfo($accessToken);
        $user = $userProvider->getUserFromToken($tokenInfo);

        $authenticator->authenticateUser($request, $accessToken, $tokenInfo, $user, 'admin');

        if ($state = $request->query->get('state')) {
            $state = $authStateEncoder->decodeState($state);

            return $this->redirect($state['redirect']);
        }

        return $this->redirectToRoute('easyadmin');
    }

    private function getRedirectUrl(): string
    {
        return $this->generateUrl('alchemy_admin_auth_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
