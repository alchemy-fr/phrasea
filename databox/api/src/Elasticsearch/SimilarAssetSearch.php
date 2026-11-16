<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\Query\Knn;
use App\Entity\Core\Asset;
use App\Service\Vector\AssetEmbeddingManager;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use FOS\ElasticaBundle\HybridResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SimilarAssetSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly TransformedFinder $finder,
        private readonly AssetEmbeddingManager $assetEmbeddingManager,
        private readonly AssetSearch $assetSearch,
    ) {
    }

    /**
     * @return array{0: Asset[], 1: array<string, float>}
     *
     * @throws NoWorkspaceAllowedException
     */
    public function findSimilar(?string $userId, array $groupIds, Asset $asset, int $limit): array
    {
        $knn = $this->createKnnQuery($userId, $groupIds, $asset, max(100, $limit * 10));
        if (null === $knn) {
            return [[], []];
        }

        $query = new Query($knn);
        $query->setSize($limit);
        $query->setSource(false);

        $assets = [];
        $scores = [];
        /** @var HybridResult[] $results */
        $results = $this->finder->findHybrid($query, $limit);
        foreach ($results as $result) {
            /** @var Asset $transformed */
            $transformed = $result->getTransformed();
            $assets[] = $transformed;
            $scores[$transformed->getId()] = $result->getResult()->getScore();
        }

        return [$assets, $scores];
    }

    /**
     * Returns null if the asset has no embedding.
     *
     * @throws NoWorkspaceAllowedException
     */
    public function createKnnQuery(?string $userId, array $groupIds, Asset $asset, int $numCandidates = 100): ?Knn
    {
        $vector = $this->assetEmbeddingManager->getAssetVector($asset);
        if (null === $vector) {
            return null;
        }

        // The base filters (ACL, attribute filter rules, trash, quarantine) MUST be applied within
        // the knn query (pre-filtering), otherwise the nearest neighbors would be computed over
        // documents the user cannot see. No search option: the trash and the quarantine are never shown.
        $filter = new Query\BoolQuery();
        foreach ($this->assetSearch->createBaseFilterQueries($userId, $groupIds) as $baseFilterQuery) {
            $filter->addFilter($baseFilterQuery);
        }
        $filter->addMustNot(new Query\Terms('_id', [$asset->getId()]));

        $knn = new Knn('embedding', $vector, $numCandidates);
        $knn->setFilter($filter);

        return $knn;
    }
}
