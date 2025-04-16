<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Elasticsearch\TagSearch;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TagCollectionProvider extends AbstractCollectionProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly TagSearch $tagSearch,
        private readonly EntityIriConverter $entityIriConverter,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaceId = $context['filters']['workspace'] ?? null;
        if (!$workspaceId) {
            $user = $this->getUser();
            $workspaces = $this->em->getRepository(Workspace::class)->getAllowedWorkspaceIds($user?->getId(), $user?->getGroups() ?? []);
        } else {
            $workspace = $this->entityIriConverter->getItemFromIri(Workspace::class, $workspaceId);
            $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);
            $workspaces = [$workspaceId];
        }

        $queryString = $context['filters']['query'] ?? null;

        if (empty($workspaces)) {
            return [];
        }

        $context['filters']['workspace'] = $workspaces;

        if (!empty($queryString)) {
            return $this->tagSearch->search($workspaces, $context['filters']);
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
