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
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
final class OAuthController extends AbstractController
{
    #[Route(path: '/auth/check', name: 'oauth_check')]
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteUserProvider $userProvider,
        AuthStateEncoder $authStateEncoder,
        RemoteAuthAuthenticatorPersister $authenticatorPersister,
    ): Response {
        [$accessToken, $refreshToken] = $oauthClient->getTokenFromAuthorizationCode(
            $request->get('code'),
            $this->generateUrl('alchemy_auth_oauth_check', [], UrlGeneratorInterface::ABSOLUTE_URL)
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
