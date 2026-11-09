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
     * Creates a publication owned by the current user in Expose: only the main properties
     * are accepted, the parent and the profile by ID.
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

        $title = $body['title'] ?? null;
        if (!is_string($title) || '' === trim($title)) {
            throw new BadRequestHttpException('The title is required');
        }
        $data = ['title' => trim($title)];
        foreach (['slug', 'description'] as $key) {
            if (null !== $value = self::getOptionalString($body, $key)) {
                $data[$key] = $value;
            }
        }
        if (array_key_exists('enabled', $body)) {
            if (!is_bool($body['enabled'])) {
                throw new BadRequestHttpException('"enabled" must be a boolean');
            }
            $data['enabled'] = $body['enabled'];
        }
        if (null !== $parent = self::getOptionalString($body, 'parent')) {
            $data['parent'] = '/publications/'.self::normalizeId($parent);
        }
        if (null !== $profile = self::getOptionalString($body, 'profile')) {
            $data['profile'] = '/publication-profiles/'.self::normalizeId($profile);
        }

        $config = $this->integrationManager->getIntegrationConfiguration($integration);
        try {
            $publication = $this->exposeClient->createPublication($config, $integrationToken, $data);
        } catch (ClientExceptionInterface $e) {
            // e.g. a slug already used: the reason given by Expose is shown to the user
            try {
                $detail = $e->getResponse()->toArray(false)['detail'] ?? null;
            } catch (\Throwable) {
                $detail = null;
            }

            throw new BadRequestHttpException(is_string($detail) ? $detail : $e->getMessage(), $e);
        }

        return new JsonResponse([
            'id' => $publication['id'],
            'title' => $publication['title'] ?? $data['title'],
        ], Response::HTTP_CREATED);
    }

    private static function getOptionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!is_string($value)) {
            throw new BadRequestHttpException(sprintf('"%s" must be a string', $key));
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * Accepts an ID or an IRI.
     */
    private static function normalizeId(string $value): string
    {
        $pos = strrpos($value, '/');

        return false === $pos ? $value : substr($value, $pos + 1);
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
