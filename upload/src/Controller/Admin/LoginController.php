<?php

namespace App\Controller\Admin;

use App\OAuth\OAuthProviderFactory;
use App\Security\OAuthUserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @Route("/admin", name="admin_")
 */
class LoginController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(
        AuthenticationUtils $authenticationUtils
    ): Response {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * @Route(path="/oauth/{provider}/authorize", name="oauth_authorize")
     */
    public function authorize(string $provider)
    {
//        $resourceOwner = $OAuthFactory->createResourceOwner($provider);
//        $redirectUri = $this->getRedirectUrl($provider);
//
//        return $this->redirect($resourceOwner->getAuthorizationUrl($redirectUri));
    }

    private function getRedirectUrl(string $provider): string
    {
        return $this->generateUrl('admin_oauth_check', [
            'provider' => $provider,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @Route(path="/oauth/{provider}/check", name="oauth_check")
     */
    public function check(
        string $provider,
        Request $request,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $dispatcher
    ) {
        // TODO
//        $resourceOwner = $OAuthFactory->createResourceOwner($provider);
//
//        $redirectUri = $this->getRedirectUrl($provider);
//
//        if ($resourceOwner->handles($request)) {
//            $accessToken = $resourceOwner->getAccessToken(
//                $request,
//                $redirectUri
//            );
//        } else {
//            throw new BadRequestHttpException('Unsupported request');
//        }
//
//        $userInformation = $resourceOwner->getUserInformation($accessToken);
//        $user = $OAuthUserProvider->loadUserByOAuthUserResponse($userInformation);
//
//        $token = new PostAuthenticationGuardToken($user, 'admin', $user->getRoles());
//        $tokenStorage->setToken($token);
//        $session->set('_security_admin', serialize($token));
//        $session->save();
//
//        $event = new InteractiveLoginEvent($request, $token);
//        $dispatcher->dispatch('security.interactive_login', $event);

        return $this->redirectToRoute('easyadmin');
    }
}
