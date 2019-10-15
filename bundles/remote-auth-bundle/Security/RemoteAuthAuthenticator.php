<?php

declare(strict_types=1);

namespace Alchemy\RemoteAuthBundle\Security;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RemoteAuthAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        SessionInterface $session,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->tokenStorage = $tokenStorage;
        $this->session = $session;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        $accessToken = RequestHelper::getAccessTokenFromRequest($request);

        return null !== $accessToken;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        $accessToken = RequestHelper::getAccessTokenFromRequest($request);

        if (null === $accessToken) {
            return null;
        }

        return [
            'token' => $accessToken,
        ];
    }

    /**
     * @param RemoteUserProvider $userProvider
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = $credentials['token'];

        if (empty($token)) {
            return null;
        }

        return $userProvider->loadUserFromAccessToken($token);
    }

    public function authenticateUser(Request $request, RemoteUser $user, string $providerKey): void
    {
        $token = $this->createAuthenticatedToken($user, $providerKey);
        $this->tokenStorage->setToken($token);

        $this->session->set('_security_'.$providerKey, serialize($token));

        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch(SecurityEvents::INTERACTIVE_LOGIN, $event);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            // you might translate this message
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
