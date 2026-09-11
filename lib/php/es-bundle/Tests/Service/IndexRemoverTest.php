<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests\Service;

use Alchemy\ESBundle\Service\IndexRemover;
use Alchemy\ESBundle\Tests\ESClientMockTrait;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class IndexRemoverTest extends TestCase
{
    use ESClientMockTrait;

    private const CLUSTER = [
        'asset_dev_2026-01-01-000000' => ['asset_dev'],   // active, aliased
        'asset_dev_2025-12-01-000000' => [],              // old generation
        'asset_dev_2025-11-01-000000' => [],              // old generation
        'asset_devil' => [],                               // starts with "asset_" but is not ours
        'collection_dev_2026-01-01-000000' => ['collection_dev'],
        'tag_dev' => [],                                   // direct index (no alias)
        '.kibana' => [],
    ];

    private function createRemover(): IndexRemover
    {
        $client = $this->createClientMock();
        $this->indices->method('getAlias')->willReturn($this->createResponse(self::aliasesResponse(self::CLUSTER)));

        return new IndexRemover(
            $this->createIndexManagerMock(['asset' => 'asset_dev', 'collection' => 'collection_dev', 'tag' => 'tag_dev']),
            $client,
        );
    }

    public function testDefaultRemovesOnlyTheAliasedIndex(): void
    {
        $plan = $this->createRemover()->collectIndicesToRemove('asset');

        $this->assertSame([
            'asset' => ['asset_dev_2026-01-01-000000' => IndexRemover::REASON_ALIASED],
        ], $plan);
    }

    public function testOldsOnlyKeepsTheAliasedIndex(): void
    {
        $plan = $this->createRemover()->collectIndicesToRemove('asset', oldsOnly: true);

        $this->assertSame([
            'asset' => [
                'asset_dev_2025-12-01-000000' => IndexRemover::REASON_OLD,
                'asset_dev_2025-11-01-000000' => IndexRemover::REASON_OLD,
            ],
        ], $plan);
    }

    public function testRemoveOldsIncludesAliasedAndOldIndices(): void
    {
        $plan = $this->createRemover()->collectIndicesToRemove('asset', removeOlds: true);

        $this->assertSame([
            'asset_dev_2026-01-01-000000' => IndexRemover::REASON_ALIASED,
            'asset_dev_2025-12-01-000000' => IndexRemover::REASON_OLD,
            'asset_dev_2025-11-01-000000' => IndexRemover::REASON_OLD,
        ], $plan['asset']);
    }

    public function testForcePrefixMatchesEveryIndexStartingWithLogicalName(): void
    {
        $plan = $this->createRemover()->collectIndicesToRemove('asset', forcePrefix: true);

        $this->assertSame([
            'asset_dev_2026-01-01-000000' => IndexRemover::REASON_PREFIXED,
            'asset_dev_2025-12-01-000000' => IndexRemover::REASON_PREFIXED,
            'asset_dev_2025-11-01-000000' => IndexRemover::REASON_PREFIXED,
            'asset_devil' => IndexRemover::REASON_PREFIXED,
        ], $plan['asset']);
    }

    public function testNoIndexArgumentCoversEveryLogicalIndex(): void
    {
        $plan = $this->createRemover()->collectIndicesToRemove();

        $this->assertSame(['asset', 'collection', 'tag'], array_keys($plan));
        $this->assertSame(['collection_dev_2026-01-01-000000' => IndexRemover::REASON_ALIASED], $plan['collection']);
        // a direct (non aliased) index is never matched by the alias lookup
        $this->assertSame([], $plan['tag']);
    }

    public function testUnknownLogicalIndexThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createRemover()->collectIndicesToRemove('nope');
    }

    public function testRemovePlannedIndicesDeletesEachOneAndReports(): void
    {
        $remover = $this->createRemover();
        $deleted = [];
        $this->indices->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (array $params) use (&$deleted) {
                $deleted[] = $params['index'];

                return $this->createResponse([]);
            });

        $output = new BufferedOutput();
        $remover->removePlannedIndices([
            'asset' => ['a_1' => IndexRemover::REASON_OLD, 'a_2' => IndexRemover::REASON_ALIASED],
            'tag' => [],
        ], $output);

        $this->assertSame(['a_1', 'a_2'], $deleted);
        $text = $output->fetch();
        $this->assertStringContainsString('Removing old index a_1', $text);
        $this->assertStringContainsString('Removing aliased index a_2', $text);
        $this->assertStringContainsString('2 indices removed!', $text);
        $this->assertStringContainsString('0 indices removed!', $text);
    }

    public function testRemoveIndicesIsCollectPlusRemove(): void
    {
        $remover = $this->createRemover();
        $deleted = [];
        $this->indices->method('delete')->willReturnCallback(function (array $params) use (&$deleted) {
            $deleted[] = $params['index'];

            return $this->createResponse([]);
        });

        $remover->removeIndices('asset', oldsOnly: true);

        $this->assertSame(['asset_dev_2025-12-01-000000', 'asset_dev_2025-11-01-000000'], $deleted);
    }

    public function testDeleteFailureIsWrappedWithTheIndexName(): void
    {
        $remover = $this->createRemover();
        $this->indices->method('delete')->willThrowException(new ClientResponseException('boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete index "a_1" with message: "boom"');

        $remover->removePlannedIndices(['asset' => ['a_1' => IndexRemover::REASON_OLD]]);
    }
}
