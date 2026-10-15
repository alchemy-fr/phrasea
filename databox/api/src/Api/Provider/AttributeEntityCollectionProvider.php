<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use App\Api\EntityIriConverter;
use App\Api\Traits\CollectionProviderAwareTrait;
use App\Api\Traits\WorkspaceCollectionTrait;
use App\Elasticsearch\AttributeEntitySearch;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeRepository;

final class AttributeEntityCollectionProvider extends AbstractCollectionProvider
{
    use CollectionProviderAwareTrait;
    use SecurityAwareTrait;
    use WorkspaceCollectionTrait;

    public function __construct(
        private readonly AttributeEntitySearch $search,
        private readonly EntityIriConverter $entityIriConverter,
        private readonly AttributeRepository $attributeRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $workspaces = $this->resolveAllowedWorkspaces($operation);
        if (empty($workspaces)) {
            return [];
        }

        // A search-as-you-type query goes to Elasticsearch, a plain listing to the ORM
        $queryString = self::getParameterValue($operation, 'query');
        if (\is_string($queryString) && '' !== trim($queryString)) {
            $result = $this->search->search($workspaces, self::getParameterValues($operation), $operation);
        } else {
            $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        $usage = self::getParameterValue($operation, 'usage');
        if (null !== $usage && '' !== $usage && filter_var($usage, FILTER_VALIDATE_BOOL)) {
            $this->assignUsage(is_iterable($result) ? $result : []);
        }

        return $result;
    }

    /**
     * Counts, per entity of the page, the attribute values referencing it (in the definitions bound to its list).
     *
     * @param iterable<AttributeEntity> $entities
     */
    private function assignUsage(iterable $entities): void
    {
        $byList = [];
        foreach ($entities as $entity) {
            if ($entity instanceof AttributeEntity) {
                $byList[$entity->getListId()][] = $entity;
            }
        }

        foreach ($byList as $listId => $listEntities) {
            $workspaceId = $listEntities[0]->getWorkspaceId();
            $definitionIds = array_map(fn ($d): string => $d->getId(), $this->attributeDefinitionRepository->getWorkspaceDefinitionOfEntity($workspaceId, $listId));
            $usage = $this->attributeRepository->countUsage($definitionIds, array_map(fn (AttributeEntity $e): string => $e->getId(), $listEntities));
            foreach ($listEntities as $entity) {
                $entity->setUsage($usage[$entity->getId()] ?? ['attributes' => 0, 'humanAttributes' => 0]);
            }
        }
    }
}
