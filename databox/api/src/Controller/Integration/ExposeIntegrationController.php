<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\Auth\IntegrationTokenTrait;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\ExposeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/integrations/expose', name: 'integration_expose_')]
class ExposeIntegrationController extends AbstractController
{
    use IntegrationTokenTrait;

    public function __construct(
        private readonly IntegrationManager $integrationManager,
        private readonly IntegrationTokenManager $integrationTokenManager,
        private readonly ExposeClient $exposeClient,
    ) {
    }

    #[Route(path: '/{integrationId}/proxy/profiles', name: 'proxy_profiles')]
    public function profilesProxy(
        string $integrationId,
        Request $request,
    ): Response {
        return $this->proxifyApi(
            $integrationId,
            'GET',
            '/publication-profiles',
            [
                'query' => $request->query->all(),
            ],
        );
    }

    #[Route(path: '/{integrationId}/proxy/publications', name: 'proxy_publications')]
    public function publicationsProxy(
        string $integrationId,
        Request $request,
    ): Response {
        return $this->proxifyApi(
            $integrationId,
            'GET',
            '/publications',
            [
                'query' => $request->query->all(),
            ],
        );
    }

    private function proxifyApi(
        string $integrationId,
        string $method,
        string $path,
        array $options,
    ): Response {
        $integration = $this->integrationManager->loadIntegration($integrationId);
        $config = $this->integrationManager->getIntegrationConfiguration($integration);

        $integrationToken = $this->getIntegrationToken($integration);

        if (null === $integrationToken) {
            throw new \InvalidArgumentException(sprintf(''));
        }

        $response = $this->exposeClient->getAuthenticatedClient($config, $integrationToken)
            ->request($method, $path, $options)
            ->toArray();

        return new JsonResponse($response);
    }
}
