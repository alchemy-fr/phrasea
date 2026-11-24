<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Integration\Auth\IntegrationTokenManager;
use App\Integration\Auth\IntegrationTokenTrait;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\ExposeClient;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use App\Security\Voter\WorkspaceIntegrationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

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

    /**
     * Creates a publication owned by the current user in Expose.
     */
    #[Route(path: '/{integrationId}/publications', name: 'create_publication', methods: ['POST'])]
    public function createPublication(
        string $integrationId,
        Request $request,
    ): Response {
        $integration = $this->integrationManager->loadIntegration($integrationId);
        if (ExposeIntegration::NAME !== $integration->getIntegration()) {
            throw new NotFoundHttpException(sprintf('Expose integration "%s" not found', $integrationId));
        }
        $this->denyAccessUnlessGranted(WorkspaceIntegrationVoter::INTERACT, $integration);

        $integrationToken = $this->getIntegrationToken($integration)
            ?? throw new AccessDeniedHttpException('Authorize the Expose integration first');

        try {
            $body = $request->toArray();
        } catch (JsonException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse(
            $this->exposeClient->createPublicationFromInput(
                $this->integrationManager->getIntegrationConfiguration($integration),
                $integrationToken,
                $body,
            ),
            Response::HTTP_CREATED,
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

        $integrationToken = $this->getIntegrationToken($integration)
            ?? throw new AccessDeniedHttpException('Authorize the Expose integration first');

        try {
            $response = $this->exposeClient->getAuthenticatedClient($config, $integrationToken)
                ->request($method, $path, $options)
                ->toArray();
        } catch (ClientExceptionInterface $e) {
            throw $this->exposeClient->createExposeError($e, $config);
        }

        return new JsonResponse($response);
    }
}
