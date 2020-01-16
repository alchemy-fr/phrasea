<?php

namespace App\Admin;

use Alchemy\AdminBundle\Auth\IdentityProvidersRegistry;
use App\Entity\AuthCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/admin")
 */
class LoginController extends AbstractController
{
    /**
     * @var string
     */
    private $authClientId;

    public function __construct(string $authClientId)
    {
        $this->authClientId = $authClientId;
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(
        AuthenticationUtils $authenticationUtils,
        IdentityProvidersRegistry $authRegistry
    ): Response {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@AlchemyAdmin/login.html.twig', [
            'site_title' => 'Auth',
            'site_logo' => null,
            'last_username' => $lastUsername,
            'error' => $error,
            'providers' => $authRegistry->getViewProviders($this->getRedirectUrl()),
        ]);
    }

    private function getRedirectUrl(): string
    {
        return $this->generateUrl(
            'alchemy_admin_auth_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Authenticates from code (in query parameters)
     * @Route(path="/auth/check", name="auth_check")
     */
    public function check(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $dispatcher
    ) {
        $code = $request->query->get('code');

        $authCode = $em->getRepository(AuthCode::class)
            ->findOneBy([
                'client' => $this->authClientId,
                'token' => $code,
            ]);
        if (!$authCode instanceof AuthCode) {
            throw new AccessDeniedHttpException('Invalid auth code');
        }
        if ($authCode->getExpiresAt() < time()) {
            throw new AccessDeniedHttpException('Auth code has expired');
        }

        $user = $authCode->getData();
        $token = new PostAuthenticationGuardToken($user, 'admin', $user->getRoles());
        $tokenStorage->setToken($token);
        $session->set('_security_admin', serialize($token));
        $session->save();

        $event = new InteractiveLoginEvent($request, $token);
        $dispatcher->dispatch($event);

        return $this->redirectToRoute('easyadmin');
    }
}
