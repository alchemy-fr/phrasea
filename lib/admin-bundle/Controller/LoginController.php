<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Alchemy\AdminBundle\Auth\IdentityProvidersRegistry;
use Alchemy\RemoteAuthBundle\Security\Provider\RemoteAuthProvider;
use Alchemy\RemoteAuthBundle\Security\RemoteAuthAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    /**
     * @var string
     */
    private $siteTitle;
    /**
     * @var string|null
     */
    private $siteLogo;

    public function __construct(string $siteTitle, ?string $siteLogo)
    {
        $this->siteTitle = $siteTitle;
        $this->siteLogo = $siteLogo;
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $authenticationUtils, IdentityProvidersRegistry $authRegistry): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@AlchemyAdmin/login.html.twig', [
            'providers' => $authRegistry->getViewProviders($this->getRedirectUrl()),
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    private function getRedirectUrl(): string
    {
        return $this->generateUrl('alchemy_admin_auth_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
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
}
