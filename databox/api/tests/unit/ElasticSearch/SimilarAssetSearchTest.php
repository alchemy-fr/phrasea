<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use App\Elasticsearch\AssetSearch;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Service\Vector\AssetEmbeddingManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Elastica\Query;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use PHPUnit\Framework\TestCase;

class SimilarAssetSearchTest extends TestCase
{
    public function testKnnFilterHoldsTheBaseFiltersOfTheAssetSearch(): void
    {
        $asset = new Asset();
        $acl = new Query\Term(['users' => 'u1']);
        $attributeFilterRule = new Query\Term(['attrs._.confidential_b' => false]);
        $notDeleted = new Query\Term(['deleted' => false]);

        $assetSearch = $this->createMock(AssetSearch::class);
        $assetSearch->expects($this->once())
            ->method('createBaseFilterQueries')
            ->with('u1', ['g1'])
            ->willReturn([$acl, $attributeFilterRule, $notDeleted]);

        $search = new SimilarAssetSearch(
            $this->createStub(TransformedFinder::class),
            $this->createEmbeddingManager([0.1, 0.2]),
            $assetSearch,
        );

        $knn = $search->createKnnQuery('u1', ['g1'], $asset, 42);
        $this->assertNotNull($knn);

        $array = $knn->toArray()['knn'];
        $this->assertSame([0.1, 0.2], $array['query_vector']);
        $this->assertSame(42, $array['num_candidates']);
        $this->assertSame([
            'bool' => [
                'filter' => [
                    $acl->toArray(),
                    $attributeFilterRule->toArray(),
                    $notDeleted->toArray(),
                ],
                'must_not' => [
                    ['terms' => ['_id' => [$asset->getId()]]],
                ],
            ],
        ], $array['filter']);
    }

    public function testNoKnnQueryWithoutEmbedding(): void
    {
        $assetSearch = $this->createMock(AssetSearch::class);
        $assetSearch->expects($this->never())->method('createBaseFilterQueries');

        $search = new SimilarAssetSearch(
            $this->createStub(TransformedFinder::class),
            $this->createEmbeddingManager(null),
            $assetSearch,
        );

        $this->assertNull($search->createKnnQuery('u1', [], new Asset()));
    }

    private function createEmbeddingManager(?array $vector): AssetEmbeddingManager
    {
        $embedding = null;
        if (null !== $vector) {
            $embedding = new AssetEmbedding();
            $embedding->setVector($vector);
        }

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($embedding);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        // Its other dependencies are final and unused by getAssetVector()
        $reflection = new \ReflectionClass(AssetEmbeddingManager::class);
        $manager = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('em')->setValue($manager, $em);

        return $manager;
    }
}
