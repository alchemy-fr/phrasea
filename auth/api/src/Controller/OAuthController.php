<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\RemoteAuthBundle\Http\AuthStateEncoder;
use App\Entity\User;
use App\OAuth\OAuthProviderFactory;
use App\Security\OAuthUserProvider;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwnerInterface;
use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Throwable;

/**
 * @Route("/oauth", name="oauth_")
 */
class OAuthController extends AbstractIdentityProviderController
{
    private OAuthUserProvider $OAuthUserProvider;
    private AuthStateEncoder $authStateEncoder;

    public function __construct(
        OAuthUserProvider $OAuthUserProvider,
        AuthStateEncoder $authStateEncoder
    )
    {
        $this->OAuthUserProvider = $OAuthUserProvider;
        $this->authStateEncoder = $authStateEncoder;
    }

    /**
     * Used direct authentication in Auth service.
     *
     * @Route(path="/{provider}/entrypoint", name="entrypoint")
     */
    public function entrypoint(string $provider, Request $request, OAuthProviderFactory $OAuthFactory)
    {
        $resourceOwner = $OAuthFactory->createResourceOwner($provider);
        $session = $request->getSession();

        $redirectUri = $session->get(SecurityController::SESSION_REDIRECT_KEY);
        $redirectUri ??= $this->generateUrl('security_index');

        return $this->redirect(
            $resourceOwner->getAuthorizationUrl(
                $this->generateOAuthRedirectUri($provider),
                [
                    'state' => $this->authStateEncoder->encodeState($redirectUri, null, true)
                ]
            )
        );
    }

    /**
     * Used for redirecting to client app (not Auth service).
     *
     * @Route(path="/{provider}/authorize", name="authorize")
     */
    public function authorize(string $provider, Request $request, OAuthProviderFactory $OAuthFactory)
    {
        $resourceOwner = $OAuthFactory->createResourceOwner($provider);

        $clientId = $request->get('client_id');
        if (!$clientId) {
            throw new BadRequestHttpException('Missing client_id parameter');
        }
        $lastRedirectUri = $request->get('redirect_uri');
        if (!$lastRedirectUri) {
            throw new BadRequestHttpException('Missing redirect_uri parameter');
        }

        $redirectUri = $this->generateUrl('oauth_check', [
            'provider' => $provider,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->redirect($resourceOwner->getAuthorizationUrl($redirectUri, [
            'state' => $this->authStateEncoder->encodeState($lastRedirectUri, $clientId),
        ]));
    }

    private function generateOAuthRedirectUri(string $provider): string
    {
        return $this->generateUrl('oauth_check', [
            'provider' => $provider,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function handleAuthorizationCodeRequestAndReturnUser(
        ResourceOwnerInterface $resourceOwner,
        Request $request,
        string $redirectUri
    ): User {
        if ($resourceOwner->handles($request)) {
            try {
                $accessToken = $resourceOwner->getAccessToken(
                    $request,
                    $redirectUri
                );
            } catch (Throwable $e) {
                $this->addFlash('error', $e->getMessage());
                throw $e;
            }
        } else {
            throw new BadRequestHttpException('Unsupported request');
        }

        $userInformation = $resourceOwner->getUserInformation($accessToken);

        return $this->OAuthUserProvider->loadUserByOAuthUserResponse($userInformation);
    }

    /**
     * @Route(path="/check/{provider}", name="check")
     */
    public function check(
        string $provider,
        Request $request,
        OAuth2 $oAuth2Server,
        OAuthProviderFactory $OAuthFactory,
        TokenStorageInterface $tokenStorage
    ) {
        $resourceOwner = $OAuthFactory->createResourceOwner($provider);

        $state = $this->authStateEncoder->decodeState($request->get('state', ''));

        $redirectUri = $this->generateUrl($request->attributes->get('_route'), [
            'provider' => $provider,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $user = $this->handleAuthorizationCodeRequestAndReturnUser(
            $resourceOwner,
            $request,
            $redirectUri
        );

        if ($state['internal']) {
            // Manually authenticate user in controller
            $firewallName = 'auth';

            $roles = $user->getRoles();
            if (!in_array('ROLE_USER', $roles, true)) {
                $roles[] = 'ROLE_USER';
            }
            $token = new PostAuthenticationGuardToken($user, $firewallName, $roles);
            $tokenStorage->setToken($token);
            $request->getSession()->set('_security_'.$firewallName, serialize($token));

            return $this->redirect($state['redirect']);
        } else {
            $scope = $request->get('scope');
            $subRequest = new Request();
            $subRequest->query->set('client_id', $state['clientId']);
            $subRequest->query->set('redirect_uri', $state['redirect']);
            $subRequest->query->set('response_type', 'code');

            try {
                return $oAuth2Server->finishClientAuthorization(true, $user, $subRequest, $scope);
            } catch (OAuth2ServerException $e) {
                throw new BadRequestHttpException($e->getMessage(), $e);
            }
        }
    }
}
