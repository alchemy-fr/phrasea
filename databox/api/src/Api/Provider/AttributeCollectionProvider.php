<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\CoreBundle\Util\DoctrineUtil;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\Elasticsearch\AttributeValueSearch;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AttributeDefinitionVoter;
use App\Service\Asset\AssetPolicy\AssetPolicyManager;

/**
 * The attribute values of an asset (every value the user may see, unpaginated), or, with one or
 * more "definition", the values of these definitions across the assets the user can read
 * (found in Elasticsearch, paginated by asset, newest assets first: a page holds every matching
 * value of its assets and the total counts the assets).
 */
class AttributeCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    private const int DEFAULT_LIMIT = 30;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private readonly AssetPolicyManager $assetPolicyManager,
        private readonly AttributeValueSearch $attributeValueSearch,
    ) {
    }

    protected function provideCollection(Operation $operation, array $uriVariables = [], array $context = []): array|object
    {
        $definitionIds = self::getParameterIds($operation, 'definition');
        if (!empty($definitionIds)) {
            return $this->provideByDefinitions($operation, $definitionIds);
        }

        $asset = $this->getAsset($operation);

        $attributes = $this->em->getRepository(Attribute::class)
            ->getCachedAssetAttributes($asset->getId())
        ;

        return array_values(array_filter($attributes, $this->createVisibilityPredicate()));
    }

    /**
     * @param string[] $definitionIds
     */
    private function provideByDefinitions(Operation $operation, array $definitionIds): object
    {
        foreach ($definitionIds as $definitionId) {
            $definition = DoctrineUtil::findStrict($this->em, AttributeDefinition::class, $definitionId, throw404: true);
            $this->denyAccessUnlessGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition);
        }

        $assetId = self::getParameterId($operation, 'assetId') ?? self::getParameterId($operation, 'asset');

        $values = self::getParameterValue($operation, 'value');
        $values = array_values(array_filter(\is_array($values) ? $values : [$values], static fn (mixed $v): bool => \is_string($v) && '' !== $v));

        $origin = self::getParameterValue($operation, 'origin');
        // The parameter schema already rejects unknown labels (422)
        $origin = \is_string($origin) && '' !== $origin ? array_search($origin, Attribute::ORIGIN_LABELS, true) : false;

        $limit = max(1, min(self::MAX_LIMIT, (int) self::getParameterValue($operation, 'limit', self::DEFAULT_LIMIT)));
        $page = max(1, (int) self::getParameterValue($operation, 'page', 1));

        $user = $this->security->getUser();
        $result = $this->attributeValueSearch->search(
            $user instanceof JwtUser ? $user->getId() : null,
            $user instanceof JwtUser ? $user->getGroups() : [],
            $definitionIds,
            $values,
            false !== $origin ? $origin : null,
            $assetId,
            $page,
            $limit,
        );

        return new TraversablePaginator(new \ArrayIterator($this->loadPage($result['assetIds'], $result['attributeIds'])), $page, $limit, $result['total']);
    }

    /**
     * The attributes found by the search, loaded by ID, in the order of their assets, minus the
     * ones the search should already have left out (unreadable asset, definition hidden by the
     * asset policies): the search applies the rights, this only guards against a stale index.
     *
     * @param string[]                $assetIds     in page order
     * @param array<string, string[]> $attributeIds by asset ID
     *
     * @return Attribute[]
     */
    private function loadPage(array $assetIds, array $attributeIds): array
    {
        $ids = array_merge(...array_values($attributeIds));
        if (empty($ids)) {
            return [];
        }

        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)->createQueryBuilder('a')
            ->addSelect('asset', 'd')
            ->innerJoin('a.asset', 'asset')
            ->innerJoin('a.definition', 'd')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $rank = array_flip($assetIds);
        usort($attributes, static fn (Attribute $a, Attribute $b): int => [$rank[$a->getAsset()->getId()] ?? PHP_INT_MAX, $a->getPosition(), $a->getCreatedAt(), $a->getId()]
            <=> [$rank[$b->getAsset()->getId()] ?? PHP_INT_MAX, $b->getPosition(), $b->getCreatedAt(), $b->getId()]);

        $isVisible = $this->createVisibilityPredicate();
        $readable = [];

        return array_values(array_filter($attributes, function (Attribute $attribute) use ($isVisible, &$readable): bool {
            $asset = $attribute->getAsset();

            return ($readable[$asset->getId()] ??= $this->security->isGranted(AbstractVoter::READ, $asset))
                && $isVisible($attribute);
        }));
    }

    /**
     * Whether the user may see an attribute of a readable asset, as in the asset output: the
     * definition policy and the asset policies. Decisions are cached for the predicate's lifetime.
     *
     * @return \Closure(Attribute): bool
     */
    private function createVisibilityPredicate(): \Closure
    {
        $filteredDefinitions = [];
        $canView = [];

        return function (Attribute $attribute) use (&$filteredDefinitions, &$canView): bool {
            $asset = $attribute->getAsset();
            $definition = $attribute->getDefinition();
            $definitionId = $definition->getId();
            $filtered = $filteredDefinitions[$asset->getId()] ??= $this->assetPolicyManager->getPolicyApplicationFilter($asset)->getFilteredAttributes();

            return !\in_array($definitionId, $filtered, true)
                && ($canView[$definitionId] ??= $this->security->isGranted(AttributeDefinitionVoter::VIEW_ATTRIBUTES, $definition));
        };
    }
}
