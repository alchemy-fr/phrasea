<?php

namespace App\Controller\Integration;

use Alchemy\AuthBundle\Security\OneTimeTokenAuthenticator;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Integration\IntegrationToken;
use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/integrations', name: 'integration_auth_')]
class IntegrationAuthController extends AbstractController
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly IntegrationManager $integrationManager,
        private readonly OneTimeTokenAuthenticator $oneTimeTokenAuthenticator,
        private readonly HttpClientInterface $client,
        private readonly IntegrationTokenManager $integrationTokenManager,
    ) {
    }

    #[Route(path: '/{integrationId}/auth', name: 'authorize')]
    public function authorizeAction(string $integrationId, Request $request): Response
    {
        $integration = $this->integrationManager->loadIntegration($integrationId);
        $options = $this->integrationManager->getIntegrationConfiguration($integration);

        $redirectUri = $this->generateUrl('integration_auth_code', [
            'integrationId' => $integrationId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $token = $request->get('token');
        if (empty($token)) {
            throw new BadRequestHttpException('Missing token in state');
        }

        return $this->redirect(sprintf(
            '%s/oauth/v2/authorize?client_id=%s&redirect_uri=%s&state=%s',
            $options['baseUrl'],
            urlencode($options['clientId']),
            urlencode($redirectUri),
            urlencode($token)
        ));
    }

    #[Route(path: '/{integrationId}/code', name: 'code')]
    public function codeAction(string $integrationId, Request $request): Response
    {
        $code = $request->get('code');
        $integration = $this->integrationManager->loadIntegration($integrationId);
        $options = $this->integrationManager->getIntegrationConfiguration($integration);

        $redirectUri = $this->generateUrl('integration_auth_code', [
            'integrationId' => $integrationId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $data = $this->client->request('POST', $options['baseUrl'].'/oauth/v2/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => $options['clientId'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
        ])->toArray();

        $token = $request->get('state');
        if (empty($token)) {
            throw new BadRequestHttpException('Missing token');
        }
        $user = $this->oneTimeTokenAuthenticator->consumeToken($token);
        $this->integrationTokenManager->persistToken($integration, $data, $user->getId());

        return $this->render('closing_popup.html.twig');
    }
}
