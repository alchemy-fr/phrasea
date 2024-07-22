<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Elasticsearch\AttributeEntitySearch;
use App\Entity\Core\Workspace;
use App\Security\Voter\AbstractVoter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AttributeEntityCollectionProvider extends AbstractCollectionProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly AttributeEntitySearch $search,
    )
    {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaceId = $context['filters']['workspace'] ?? null;
        if (empty($workspaceId)) {
            throw new BadRequestHttpException('Missing workspace');
        }
        $workspace = DoctrineUtil::findStrict($this->em, Workspace::class, $workspaceId);
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $workspace);

        $queryString = $context['filters']['query'] ?? null;

        if (!empty($queryString)) {
            return $this->search->search($workspaceId, $context['filters'] ?? []);
        }

        return $this->collectionProvider->provide($operation, $uriVariables, $context);
    }
}
