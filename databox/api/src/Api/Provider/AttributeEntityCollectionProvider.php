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
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use App\Service\Image\ImageSetManager;

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
        private readonly ImageSetManager $imageSetManager,
        private readonly AttributeEntityRepository $attributeEntityRepository,
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
            $result = new PagerFantaApiPlatformPaginator($this->search->search($workspaces, self::getParameterValues($operation), $operation));
        } else {
            $result = $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        $entities = [];
        foreach (is_iterable($result) ? $result : [] as $entity) {
            if ($entity instanceof AttributeEntity) {
                $entities[] = $entity;
            }
        }
        $this->imageSetManager->preload(array_map(fn (AttributeEntity $entity) => $entity->getImage(), $entities));
        $this->preloadHierarchy($entities);

        $usage = self::getParameterValue($operation, 'usage');
        if (null !== $usage && '' !== $usage && filter_var($usage, FILTER_VALIDATE_BOOL)) {
            $this->assignUsage(is_iterable($result) ? $result : []);
        }

        return $result;
    }

    /**
     * Loads the ancestors of the page at once (AttributeEntity::getPath() walks them) and counts the children.
     *
     * @param AttributeEntity[] $entities
     */
    private function preloadHierarchy(array $entities): void
    {
        $classified = array_values(array_filter($entities, fn (AttributeEntity $e): bool => null !== $e->getParent() || $e->isClassified()));
        if (empty($classified)) {
            return;
        }

        $ids = array_map(fn (AttributeEntity $e): string => $e->getId(), $classified);
        $ancestorIds = array_values(array_unique(array_merge(...array_values($this->attributeEntityRepository->getAncestorIds($ids)))));
        if (!empty($ancestorIds)) {
            $this->attributeEntityRepository->findBy(['id' => $ancestorIds]);
        }

        $childCounts = $this->attributeEntityRepository->countChildren($ids);
        foreach ($classified as $entity) {
            $entity->setChildCount($childCounts[$entity->getId()] ?? 0);
        }
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
