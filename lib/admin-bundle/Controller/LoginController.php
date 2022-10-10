<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Auth\OAuthClient;
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
    public function login(OAuthClient $OAuthClient, Request $request): Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), 'admin');
        $finalRedirectUri = $request->get('r', $targetPath);

        return $this->redirect($OAuthClient->getAuthorizeUrl($this->getRedirectUrl(), http_build_query([
            'r' => $finalRedirectUri,
        ])));
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

        if ($state = $request->query->get('state')) {
            parse_str($state, $statePayload);
            if (isset($statePayload['r'])) {
                return $this->redirect($statePayload['r']);
            }
        }

        return $this->redirectToRoute('easyadmin');
    }

    private function getRedirectUrl(): string
    {
        return $this->generateUrl('alchemy_admin_auth_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
