<?php

declare(strict_types=1);

namespace App\Controller\Integration;

use App\Entity\Core\AttributeEntity;
use App\Entity\Core\Collection;
use App\Entity\Core\EntityList;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Uploader\Message\IngestUploaderCommit;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Repository\Core\AttributeEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Called by the Uploader with the security key of the integration (`Authorization: ApiKey <key>`):
 * no user is behind these requests.
 */
#[Route(path: '/integrations/uploader', name: 'integration_uploader_')]
class UploaderIntegrationController extends AbstractController
{
    private const int DEFAULT_LIMIT = 30;
    private const int MAX_LIMIT = 100;

    #[Route(path: '/{integrationId}/incoming-commit', name: 'incoming_commit', methods: ['POST'])]
    public function incomingRenditionAction(
        string $integrationId,
        Request $request,
        MessageBusInterface $bus,
        IntegrationManager $integrationManager,
    ): Response {
        $this->authenticate($integrationId, $request, $integrationManager);

        $data = json_decode($request->getContent(), true);

        $commitId = $data['commit_id'] ?? null;
        if (!$commitId) {
            throw new BadRequestHttpException('Missing commit_id');
        }
        $token = $data['token'] ?? null;
        if (!$token) {
            throw new BadRequestHttpException('Missing token');
        }

        $bus->dispatch(new IngestUploaderCommit(
            $integrationId,
            $commitId,
            $token
        ));

        return new Response();
    }

    /**
     * The approved values of an entity list of the workspace of the integration, for the forms
     * filled through an upload link (nobody to call Databox on behalf of).
     */
    #[Route(path: '/{integrationId}/attribute-entities', name: 'attribute_entities', methods: ['GET'])]
    public function attributeEntitiesAction(
        string $integrationId,
        Request $request,
        IntegrationManager $integrationManager,
        EntityManagerInterface $em,
        AttributeEntityRepository $repository,
    ): JsonResponse {
        $config = $this->authenticate($integrationId, $request, $integrationManager);

        $listId = $request->query->get('list');
        if (!is_string($listId) || '' === $listId) {
            throw new BadRequestHttpException('Missing list');
        }

        $workspaceId = $this->getWorkspaceId($config, $em);
        $list = Uuid::isValid($listId) ? $em->find(EntityList::class, $listId) : null;
        if (null === $workspaceId || !$list instanceof EntityList || $list->getWorkspaceId() !== $workspaceId) {
            throw new NotFoundHttpException('Entity list not found');
        }

        $ids = $request->query->all()['ids'] ?? [];
        $ids = array_values(array_unique(array_filter(
            is_array($ids) ? $ids : [$ids],
            static fn (mixed $id): bool => is_string($id) && Uuid::isValid($id),
        )));
        if (count($ids) > self::MAX_LIMIT) {
            throw new BadRequestHttpException(sprintf('At most %d ids', self::MAX_LIMIT));
        }
        $query = $request->query->get('query');
        $query = is_string($query) && '' !== trim($query) ? trim($query) : null;
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));

        if ($request->query->has('ids') && [] === $ids) {
            $entities = [];
        } else {
            $entities = $repository->findApprovedOfList($list, $query, $ids, $limit + 1, ($page - 1) * $limit);
        }

        $data = [
            'member' => array_map(static fn (AttributeEntity $entity): array => [
                'id' => $entity->getId(),
                'value' => $entity->getValue(),
                'translations' => $entity->getTranslations() ?? [],
                'color' => $entity->getColor(),
                'emoji' => $entity->getEmoji(),
            ], array_slice($entities, 0, $limit)),
        ];
        if (count($entities) > $limit) {
            $data['view'] = [
                'next' => $this->generateUrl('integration_uploader_attribute_entities', array_filter([
                    'integrationId' => $integrationId,
                    'list' => $list->getId(),
                    'query' => $query,
                    'ids' => [] !== $ids ? $ids : null,
                    'page' => $page + 1,
                    'limit' => $limit,
                ], static fn (mixed $v): bool => null !== $v)),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @throws UnauthorizedHttpException without key
     * @throws NotFoundHttpException     when the integration is unknown, disabled or not an Uploader one
     * @throws AccessDeniedHttpException when the key is not the security key of the integration
     */
    private function authenticate(string $integrationId, Request $request, IntegrationManager $integrationManager): IntegrationConfig
    {
        $authToken = preg_replace('#^ApiKey\s+#', '', (string) $request->headers->get('Authorization', ''));
        if (!$authToken) {
            throw new UnauthorizedHttpException('ApiKey', 'Missing ApiKey token');
        }

        try {
            $integration = $integrationManager->loadIntegration($integrationId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException('Integration not found');
        }
        if (UploaderIntegration::NAME !== $integration->getIntegration() || !$integration->isEnabled()) {
            throw new NotFoundHttpException('Integration not found');
        }

        $config = $integrationManager->getIntegrationConfiguration($integration);
        if (!$config->matches('securityKey', $authToken)) {
            throw new AccessDeniedHttpException('Invalid token');
        }

        return $config;
    }

    /**
     * The workspace of the integration, or else the one of its destination collection.
     */
    private function getWorkspaceId(IntegrationConfig $config, EntityManagerInterface $em): ?string
    {
        $workspaceId = $config->getWorkspaceId();
        if (null !== $workspaceId) {
            return $workspaceId;
        }

        $collectionId = $config['collectionId'] ?? null;
        if (!is_string($collectionId) || !Uuid::isValid($collectionId)) {
            return null;
        }

        return $em->find(Collection::class, $collectionId)?->getWorkspaceId();
    }
}
