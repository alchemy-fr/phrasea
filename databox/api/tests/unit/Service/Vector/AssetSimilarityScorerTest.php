<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Vector;

use App\Elasticsearch\AQL\AQLParser;
use App\Service\Vector\AssetSimilarityScorer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AssetSimilarityScorerTest extends TestCase
{
    private const string ASSET_ID = 'fa2a50ea-ec37-45a1-8fbc-227344e25e9d';
    private const string OTHER_ASSET_ID = '2b2c8b1e-0000-4000-8000-0000000000ff';

    #[DataProvider('getReferenceCases')]
    public function testGetReferenceAssetId(array $conditions, ?string $expected): void
    {
        $scorer = new AssetSimilarityScorer($this->createStub(EntityManagerInterface::class), new AQLParser());

        $this->assertSame($expected, $scorer->getReferenceAssetId($conditions));
    }

    public static function getReferenceCases(): array
    {
        $similar = sprintf('@similar = "%s"', self::ASSET_ID);

        return [
            'none' => [[], null],
            'other condition' => [['@workspace = "foo"'], null],
            'similar' => [[$similar], self::ASSET_ID],
            'similar among filters' => [['@workspace = "foo"', $similar], self::ASSET_ID],
            'similar as an object' => [[['query' => $similar]], self::ASSET_ID],
            'same reference twice' => [[$similar, $similar], self::ASSET_ID],
            'disabled' => [[['query' => $similar, 'disabled' => true]], null],
            'inverted' => [[['query' => $similar, 'inversed' => true]], null],
            'negated' => [[sprintf('NOT (%s)', $similar)], null],
            'combined' => [[sprintf('%s OR @workspace = "foo"', $similar)], null],
            'two references' => [[$similar, sprintf('@similar = "%s"', self::OTHER_ASSET_ID)], null],
        ];
    }
}
