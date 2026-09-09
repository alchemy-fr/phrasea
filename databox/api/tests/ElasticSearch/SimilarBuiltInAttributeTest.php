<?php

declare(strict_types=1);

namespace App\Tests\ElasticSearch;

use App\Elasticsearch\AQL\ConditionOperatorEnum;
use App\Elasticsearch\BuiltInAttribute\SimilarBuiltInAttribute;
use App\Elasticsearch\Query\Knn;
use App\Elasticsearch\SimilarAssetSearch;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use Elastica\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SimilarBuiltInAttributeTest extends TestCase
{
    private const string ASSET_ID = 'fa2a50ea-ec37-45a1-8fbc-227344e25e9d';

    public function testCreateFilterQueryReturnsKnnForReadableAsset(): void
    {
        $asset = new Asset();
        $knn = new Knn('embedding', [0.1, 0.2], 500);

        $attribute = $this->createAttribute($asset, granted: true, knn: $knn);

        $query = $attribute->createFilterQuery(self::ASSET_ID, ConditionOperatorEnum::EQUALS, [
            'userId' => 'user-1',
            'groupIds' => ['group-1'],
        ]);

        $this->assertSame($knn, $query);
    }

    public function testCreateFilterQueryReturnsMatchNoneWhenAssetIsMissing(): void
    {
        $attribute = $this->createAttribute(null, granted: false, knn: null);

        $query = $attribute->createFilterQuery(self::ASSET_ID, ConditionOperatorEnum::EQUALS, []);

        $this->assertInstanceOf(Query\MatchNone::class, $query);
    }

    public function testCreateFilterQueryReturnsMatchNoneWhenAssetIsNotReadable(): void
    {
        $attribute = $this->createAttribute(new Asset(), granted: false, knn: null);

        $query = $attribute->createFilterQuery(self::ASSET_ID, ConditionOperatorEnum::EQUALS, []);

        $this->assertInstanceOf(Query\MatchNone::class, $query);
    }

    public function testCreateFilterQueryReturnsMatchNoneWhenAssetHasNoEmbedding(): void
    {
        $attribute = $this->createAttribute(new Asset(), granted: true, knn: null);

        $query = $attribute->createFilterQuery(self::ASSET_ID, ConditionOperatorEnum::EQUALS, []);

        $this->assertInstanceOf(Query\MatchNone::class, $query);
    }

    public function testCreateFilterQueryRejectsUnsupportedOperator(): void
    {
        $attribute = $this->createAttribute(new Asset(), granted: true, knn: null);

        $this->expectException(BadRequestHttpException::class);
        $attribute->createFilterQuery(self::ASSET_ID, ConditionOperatorEnum::NOT_EQUALS, []);
    }

    public function testCreateFilterQueryRejectsNonStringValue(): void
    {
        $attribute = $this->createAttribute(new Asset(), granted: true, knn: null);

        $this->expectException(BadRequestHttpException::class);
        $attribute->createFilterQuery(['foo'], ConditionOperatorEnum::EQUALS, []);
    }

    private function createAttribute(?Asset $asset, bool $granted, ?Knn $knn): SimilarBuiltInAttribute
    {
        $repository = $this->createMock(AssetRepository::class);
        $repository->method('find')->willReturn($asset);

        $similarAssetSearch = $this->createMock(SimilarAssetSearch::class);
        $similarAssetSearch->method('createKnnQuery')->willReturn($knn);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $attribute = new SimilarBuiltInAttribute($repository, $similarAssetSearch);
        $attribute->setSecurity($security);

        return $attribute;
    }
}
