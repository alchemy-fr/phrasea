<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\BuiltInAttribute\AssetStatusBuiltInAttribute;
use App\Elasticsearch\BuiltInAttribute\DeletedBuiltInAttribute;
use App\Elasticsearch\Query\Knn;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Service\Vector\AssetEmbeddingManager;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use FOS\ElasticaBundle\HybridResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SimilarAssetSearch extends AbstractSearch
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.asset')]
        private readonly TransformedFinder $finder,
        private readonly AssetEmbeddingManager $assetEmbeddingManager,
        private readonly DeletedBuiltInAttribute $deletedBuiltInAttribute,
        private readonly AssetStatusBuiltInAttribute $assetStatusBuiltInAttribute,
    ) {
    }

    /**
     * @return array{0: Asset[], 1: array<string, float>}
     *
     * @throws NoWorkspaceAllowedException
     */
    public function findSimilar(?string $userId, array $groupIds, Asset $asset, int $limit): array
    {
        $vector = $this->assetEmbeddingManager->getAssetVector($asset);
        if (null === $vector) {
            return [[], []];
        }

        $options = [
            'userId' => $userId,
            'groupIds' => $groupIds,
        ];

        $filter = new Query\BoolQuery();

        // The ACL filter MUST be applied within the knn query (pre-filtering),
        // otherwise the nearest neighbors would be computed over unauthorized documents.
        $aclBoolQuery = $this->createACLBoolQuery($userId, $groupIds);
        if (null !== $aclBoolQuery) {
            $filter->addFilter($aclBoolQuery);
        }

        $filter->addFilter($this->deletedBuiltInAttribute->createFilterQuery(false, ConditionOperatorEnum::EQUALS, $options));
        $filter->addFilter($this->assetStatusBuiltInAttribute->createFilterQuery(AssetStatusEnum::Accepted, ConditionOperatorEnum::EQUALS, $options));
        $filter->addMustNot(new Query\Terms('_id', [$asset->getId()]));

        $knn = new Knn('embedding', $vector, max(100, $limit * 10));
        $knn->setFilter($filter);

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
}
