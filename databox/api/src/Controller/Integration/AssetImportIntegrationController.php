<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Entity\Core\AssetImport;
use App\Entity\Core\Collection;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\AssetImport\AssetImportIntegration;
use App\Integration\IntegrationManager;
use App\Service\Import\AssetImportManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives the import instructions sent to an "Asset import" integration.
 */
#[Route(path: '/integrations/asset-import', name: 'integration_asset_import_')]
class AssetImportIntegrationController extends AbstractController
{
    private const array FORMATS_BY_CONTENT_TYPE = [
        'application/x-ndjson' => AssetImport::FORMAT_NDJSON,
        'application/jsonl' => AssetImport::FORMAT_NDJSON,
        'application/jsonlines' => AssetImport::FORMAT_NDJSON,
        'application/json' => AssetImport::FORMAT_JSON,
    ];

    #[Route(path: '/{integrationId}/imports', name: 'webhook', methods: ['POST'])]
    public function webhookAction(
        string $integrationId,
        Request $request,
        EntityManagerInterface $em,
        IntegrationManager $integrationManager,
        AssetImportManager $importManager,
    ): Response {
        $authToken = preg_replace('#^ApiKey\s+#i', '', trim((string) $request->headers->get('Authorization', '')), 1, $count);
        if (0 === $count || '' === $authToken) {
            throw new UnauthorizedHttpException('ApiKey', 'Missing "Authorization: ApiKey <key>" header');
        }

        $integration = Uuid::isValid($integrationId) ? $em->find(WorkspaceIntegration::class, $integrationId) : null;
        if (!$integration instanceof WorkspaceIntegration
            || AssetImportIntegration::NAME !== $integration->getIntegration()
            || !$integration->isEnabled()
            || null === $integration->getWorkspace()
        ) {
            throw new NotFoundHttpException('Integration not found');
        }

        $config = $integrationManager->getIntegrationConfiguration($integration);
        if (!hash_equals((string) ($config['securityKey'] ?? ''), $authToken)) {
            throw new AccessDeniedHttpException('Invalid ApiKey');
        }

        $contentType = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type', ''))[0]));
        $format = self::FORMATS_BY_CONTENT_TYPE[$contentType] ?? throw new UnsupportedMediaTypeHttpException(sprintf('Unsupported Content-Type "%s": use one of %s', $contentType, implode(', ', array_keys(self::FORMATS_BY_CONTENT_TYPE))));

        $workspace = $integration->getWorkspace();

        // The request body may not be seekable: buffered (on disk above 2 MB) to be measured
        $body = fopen('php://temp', 'w+');
        try {
            if (0 === stream_copy_to_stream($request->getContent(true), $body)) {
                throw new BadRequestHttpException('Empty body');
            }
            rewind($body);

            $sourcePath = $importManager->storeContent($workspace, $format, $body);
        } finally {
            fclose($body);
        }

        $collection = null;
        if (null !== $collectionId = $config['collectionId'] ?? null) {
            $collection = $em->find(Collection::class, $collectionId);
        }

        $import = $importManager->createImport(
            $workspace,
            $format,
            $sourcePath,
            $integration->getOwnerId(),
            collection: $collection,
            privacy: $config['privacy'] ?? null,
            integration: $integration,
        );
        $em->flush();

        return new JsonResponse(['id' => $import->getId()], Response::HTTP_ACCEPTED);
    }
}
