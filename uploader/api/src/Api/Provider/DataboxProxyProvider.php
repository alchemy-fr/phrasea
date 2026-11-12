<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Databox\DataboxCatalog;
use App\Databox\DataboxException;
use App\Entity\Target;
use App\Model\DataboxProxy;
use App\Security\Voter\FormDataEditorVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The Databox catalog of the form builder.
 */
final readonly class DataboxProxyProvider implements ProviderInterface
{
    use DataboxResponseTrait;

    public function __construct(
        private DataboxCatalog $catalog,
        private Security $security,
        private EntityManagerInterface $em,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        if (!$this->security->isGranted(FormDataEditorVoter::EDIT_FORM_SCHEMA)) {
            throw new AccessDeniedHttpException('Only form editors can browse Databox');
        }

        /** @var Request $request */
        $request = $context['request'];
        $id = (string) ($uriVariables['id'] ?? '');
        $query = self::optionsQuery($request);

        return self::fromDatabox(fn (): array => match ($operation->getName()) {
            DataboxProxy::WORKSPACES => $this->catalog->getWorkspaces(),
            DataboxProxy::WORKSPACE => $this->catalog->getWorkspace($id),
            DataboxProxy::WORKSPACE_FIELDS => $this->catalog->getFields($id),
            DataboxProxy::TARGET_DESTINATION => $this->getTargetDestination($id),
            DataboxProxy::WORKSPACE_TAGS => $this->catalog->getTagOptions($id, $query['query'], $query['ids'], $query['page']),
            DataboxProxy::ENTITY_LIST_VALUES => $this->catalog->getEntityOptions($id, $query['query'], $query['ids'], $query['page'], $request->getLocale()),
            default => throw new \LogicException(sprintf('Unsupported operation "%s"', $operation->getName())),
        });
    }

    /**
     * The Databox collection the target uploads to by default, which tells the builder its workspace.
     *
     * @return array{collection: ?array{id: string, title: string, workspaceId: ?string}}
     */
    private function getTargetDestination(string $targetId): array
    {
        $target = $this->em->find(Target::class, $targetId);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $targetId));
        }

        $collectionId = $target->getDefaultDestination();
        if (null === $collectionId || '' === $collectionId) {
            return ['collection' => null];
        }

        try {
            return ['collection' => $this->catalog->getCollection($collectionId)];
        } catch (DataboxException $e) {
            // The destination may not be a Databox collection (another kind of target)
            if (404 === $e->statusCode || 400 === $e->statusCode) {
                return ['collection' => null];
            }
            throw $e;
        }
    }
}
