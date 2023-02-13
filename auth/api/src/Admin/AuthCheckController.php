<?php

namespace App\Admin;

use Alchemy\OAuthServerBundle\Entity\AuthCode;
use Alchemy\RemoteAuthBundle\Http\AuthStateEncoder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route("/admin")
 */
class AuthCheckController extends AbstractController
{
    private string $authClientId;

    public function __construct(string $authClientId)
    {
        $this->authClientId = $authClientId;
    }

    /**
     * Authenticates from code (in query parameters).
     *
     * @Route(path="/auth/check", name="auth_check")
     */
    public function check(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $dispatcher,
        AuthStateEncoder $authStateEncoder
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

        if ($state = $request->query->get('state')) {
            $state = $authStateEncoder->decodeState($state);

            return $this->redirect($state['redirect']);
        }

        return $this->redirectToRoute('easyadmin');
    }
}
