<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\OAuth\OAuthClient;
use Alchemy\AdminBundle\OAuth\OAuthRegistry;
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
    public function login(AuthenticationUtils $authenticationUtils, OAuthRegistry $authRegistry): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@AlchemyAdmin/login.html.twig', [
            'providers' => $authRegistry->getViewProviders($this->getRedirectUri()),
            'site_title' => $this->siteTitle,
            'site_logo' => $this->siteLogo,
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    private function getRedirectUri(): string
    {
        return $this->generateUrl('alchemy_admin_oauth_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @Route("/oauth/check", name="oauth_check")
     */
    public function oauthCheck(
        Request $request,
        OAuthClient $oauthClient,
        RemoteAuthProvider $userProvider,
        RemoteAuthAuthenticator $authenticator
    ): Response
    {
        $accessToken = $oauthClient->getAccessTokenFromAuthorizationCode(
            $request->get('code'),
            $this->getRedirectUri()
        );

        $user = $userProvider->getTokenInfo($accessToken);
        $authenticator->authenticateUser($request, $user, 'admin');

        return $this->redirectToRoute('easyadmin');
    }
}
