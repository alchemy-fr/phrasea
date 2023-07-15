<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Controller;

use Alchemy\AuthBundle\Client\OAuthClient;
use Alchemy\AuthBundle\Http\AuthStateEncoder;
use Alchemy\AuthBundle\Security\RemoteAuthAuthenticatorPersister;
use Alchemy\AuthBundle\Security\RemoteUserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class OAuthController extends AbstractController
{
    public function __construct()
    {
    }

    #[Route(path: '/auth/check', name: 'auth_check')]
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteUserProvider $userProvider,
        AuthStateEncoder $authStateEncoder,
        RemoteAuthAuthenticatorPersister $authenticatorPersister
    ): Response {
        [$accessToken, $refreshToken] = $oauthClient->getTokenFromAuthorizationCode(
            $request->get('code'),
            $request->getUri()
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
}
