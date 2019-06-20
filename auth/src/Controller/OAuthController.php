<?php

declare(strict_types=1);

namespace App\Controller;

use App\OAuth\OAuthProviderFactory;
use App\Security\OAuthUserProvider;
use FOS\OAuthServerBundle\Event\OAuthEvent;
use FOS\OAuthServerBundle\Model\ClientInterface;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use OAuth2\OAuth2;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route("/oauth", name="oauth_")
 */
class OAuthController extends AbstractController
{
    /**
     * @var OAuth2
     */
    private $oAuth2Server;

    public function __construct(OAuth2 $oAuth2Server)
    {
        $this->oAuth2Server = $oAuth2Server;
    }

    /**
     * @Route(path="/{provider}/authorize", name="authorize")
     */
    public function authorize(string $provider, Request $request, OAuthProviderFactory $OAuthFactory)
    {
        $resourceOwner = $OAuthFactory->createResourceOwner($provider);

        $redirectUri = $this->generateUrl('oauth_check', $this->getRedirectParams(
            $provider,
            $request->get('redirect_uri'),
            $request->get('client_id')
        ), UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->redirect($resourceOwner->getAuthorizationUrl($redirectUri));
    }

    private function getRedirectParams(
        string $provider,
        string $redirectUri,
        string $clientId
    ): array
    {
        return [
            'provider' => $provider,
            'r' => $redirectUri,
            'cid' => $clientId,
        ];
    }

    /**
     * @Route(path="/{provider}/check", name="check")
     */
    public function check(
        string $provider,
        Request $request,
        OAuthProviderFactory $OAuthFactory,
        OAuthUserProvider $OAuthUserProvider,
        EventDispatcherInterface $eventDispatcher,
        ClientManagerInterface $clientManager
    )
    {
        $resourceOwner = $OAuthFactory->createResourceOwner($provider);

        $finalRedirectUri = $request->get('r');
        $clientId = $request->get('cid');

        $redirectUri = $this->generateUrl('oauth_check', $this->getRedirectParams(
            $provider,
            $finalRedirectUri,
            $clientId
        ), UrlGeneratorInterface::ABSOLUTE_URL);

        if ($resourceOwner->handles($request)) {
            $accessToken = $resourceOwner->getAccessToken(
                $request,
                $redirectUri
            );
        } else {
            throw new BadRequestHttpException('Unsupported request');
        }

        $userInformation = $resourceOwner->getUserInformation($accessToken);
        $user = $OAuthUserProvider->loadUserByOAuthUserResponse($userInformation);

        $scope = $request->get('scope');
        $subRequest = new Request();
        $subRequest->query->set('client_id', $clientId);
        $subRequest->query->set('redirect_uri', $finalRedirectUri);
        $subRequest->query->set('response_type', 'code');

        return $this->oAuth2Server->finishClientAuthorization(true, $user, $subRequest, $scope);
    }
}
