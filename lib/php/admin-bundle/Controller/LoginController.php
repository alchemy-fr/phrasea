<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Alchemy\RemoteAuthBundle\Http\AuthStateEncoder;
use Alchemy\RemoteAuthBundle\Security\RemoteAuthAuthenticatorPersister;
use Alchemy\RemoteAuthBundle\Security\RemoteUserProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginController extends AbstractAdminController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'login')]
    public function login(Request $request, OAuthClient $OAuthClient, AuthStateEncoder $authStateEncoder): Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), 'admin');

        $target = $request->get('r', $targetPath);

        return $this->redirect($OAuthClient->getAuthorizeUrl(
            $this->getRedirectUrl(),
            $target ? $authStateEncoder->encodeState($target) : null
        ));
    }

    #[Route(path: '/auth/check', name: 'auth_check')]
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteUserProvider $userProvider,
        AuthStateEncoder $authStateEncoder,
        RemoteAuthAuthenticatorPersister $authenticatorPersister
    ): Response {
        [$accessToken, $refreshToken] = $oauthClient->getAccessTokenFromAuthorizationCode(
            $request->get('code'),
            $this->getRedirectUrl()
        );

        $user = $userProvider->loadUserByIdentifier($accessToken);

        $authenticatorPersister->authenticateUser($request, $accessToken, $refreshToken, [], $user, 'admin');

        if ($state = $request->query->get('state')) {
            $state = $authStateEncoder->decodeState($state);

            return $this->redirect($state['redirect']);
        }

        return $this->redirectToRoute('easyadmin');
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): never
    {
        // controller can be blank: it will never be called!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    private function getRedirectUrl(): string
    {
        return $this->generateUrl('alchemy_admin_auth_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
