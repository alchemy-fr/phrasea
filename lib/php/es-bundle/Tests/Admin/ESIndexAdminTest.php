<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests\Admin;

use Alchemy\ESBundle\Admin\ESIndexAdmin;
use Alchemy\ESBundle\Service\IndexRemover;
use Alchemy\ESBundle\Tests\ESClientMockTrait;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Configuration\IndexConfig;
use FOS\ElasticaBundle\Elastica\Client;
use FOS\ElasticaBundle\Index\Resetter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ESIndexAdminTest extends TestCase
{
    use ESClientMockTrait;

    private const LOGICAL = ['asset' => 'asset_dev', 'tag' => 'tag_dev', 'basket' => 'basket_dev'];

    private const ALIASES = [
        'asset_dev_2026-01-01-000000' => ['asset_dev', 'asset_snapshot'],
        'asset_dev_2025-12-01-000000' => [],
        'tag_dev' => [],
        'other_app' => ['asset_snapshot'],
        '.kibana' => [],
    ];

    private const CAT = [
        ['index' => 'tag_dev', 'health' => 'green', 'status' => 'open', 'docs.count' => '4', 'store.size' => '2048', 'pri' => '1', 'rep' => '0', 'creation.date' => '1767225600000'],
        ['index' => 'asset_dev_2026-01-01-000000', 'health' => 'yellow', 'status' => 'open', 'docs.count' => '117', 'store.size' => '172000', 'pri' => '1', 'rep' => '1', 'creation.date' => '1767225600000'],
        ['index' => 'asset_dev_2025-12-01-000000', 'health' => 'yellow', 'status' => 'close', 'docs.count' => '100', 'store.size' => '1000', 'pri' => '1', 'rep' => '1', 'creation.date' => '1764547200000'],
        ['index' => '.kibana', 'health' => 'green', 'status' => 'open', 'docs.count' => '0', 'store.size' => '227', 'pri' => '1', 'rep' => '0', 'creation.date' => '1764547200000'],
        ['index' => 'other_app', 'health' => 'red', 'status' => 'open', 'docs.count' => '1', 'store.size' => '227', 'pri' => '1', 'rep' => '0', 'creation.date' => '1764547200000'],
    ];

    /** @var Client&MockObject */
    private Client $client;
    /** @var Resetter&MockObject */
    private Resetter $resetter;

    private function createAdmin(array $aliases = self::ALIASES, array $cat = self::CAT, string $prefix = ''): ESIndexAdmin
    {
        $this->client = $this->createClientMock();
        $this->indices->method('getAlias')->willReturn($this->createResponse(self::aliasesResponse($aliases)));
        $this->cat->method('indices')->willReturn($this->createResponse($cat));

        $configManager = $this->createMock(ConfigManager::class);
        $configManager->method('getIndexConfiguration')->willReturnCallback(function (string $name): IndexConfig {
            $config = $this->createMock(IndexConfig::class);
            $config->method('isUseAlias')->willReturn('asset' === $name);

            return $config;
        });

        $this->resetter = $this->createMock(Resetter::class);
        $indexManager = $this->createIndexManagerMock(self::LOGICAL);

        return new ESIndexAdmin(
            $indexManager,
            $configManager,
            $this->client,
            $this->resetter,
            new IndexRemover($indexManager, $this->client),
            $prefix,
        );
    }

    public function testWithoutPrefixEverythingIsManaged(): void
    {
        $admin = $this->createAdmin();

        $this->assertSame('', $admin->getIndexPrefix());
        $this->assertTrue($admin->isManaged('anything'));
        $this->assertTrue($admin->isManaged('.kibana'));
    }

    public function testPrefixRestrictsPhysicalIndicesAndAliases(): void
    {
        $admin = $this->createAdmin(prefix: 'asset_');
        $this->cat->expects($this->once())->method('indices')
            ->with($this->callback(fn (array $params): bool => 'asset_*' === $params['index']));

        $physical = array_column($admin->getPhysicalIndices(), 'name');
        $this->assertSame(['asset_dev_2025-12-01-000000', 'asset_dev_2026-01-01-000000'], $physical, 'tag_dev, other_app and .kibana are hidden');

        // the alias on "other_app" (outside the prefix) is not listed
        $this->assertSame([
            'asset_dev' => ['asset_dev_2026-01-01-000000'],
            'asset_snapshot' => ['asset_dev_2026-01-01-000000'],
        ], $admin->getAliases());

        $this->assertTrue($admin->isManaged('asset_dev_2026-01-01-000000'));
        $this->assertFalse($admin->isManaged('tag_dev'));
        $this->assertFalse($admin->isManaged('.kibana'));
    }

    public function testPrefixHidesAliasesOutsideThePrefixOnManagedIndices(): void
    {
        $admin = $this->createAdmin(
            aliases: ['pfx_index' => ['pfx_alias', 'other_alias'], 'other_index' => ['pfx_stray']],
            cat: [['index' => 'pfx_index', 'health' => 'green', 'status' => 'open', 'docs.count' => '1', 'store.size' => '1', 'pri' => '1', 'rep' => '0', 'creation.date' => '1767225600000']],
            prefix: 'pfx_',
        );

        $physical = $admin->getPhysicalIndices();
        $this->assertCount(1, $physical);
        $this->assertSame(['pfx_alias'], $physical[0]['aliases']);
        $this->assertSame(['pfx_alias' => ['pfx_index']], $admin->getAliases(), 'an in-scope alias on an out-of-scope index stays hidden');
    }

    public function testSwitchAliasIgnoresIndicesOutsideThePrefix(): void
    {
        $admin = $this->createAdmin(prefix: 'asset_');
        $actions = null;
        $this->indices->method('updateAliases')->willReturnCallback(function (array $params) use (&$actions) {
            $actions = $params['body']['actions'];

            return $this->createResponse([]);
        });

        $previous = $admin->switchAlias('asset_snapshot', 'asset_dev_2025-12-01-000000');

        $this->assertSame(['asset_dev_2026-01-01-000000'], $previous, '"other_app" is out of scope and left untouched');
        $this->assertSame([
            ['remove' => ['index' => 'asset_dev_2026-01-01-000000', 'alias' => 'asset_snapshot']],
            ['add' => ['index' => 'asset_dev_2025-12-01-000000', 'alias' => 'asset_snapshot']],
        ], $actions);
    }

    public function testPhysicalIndicesAreSortedAndClassified(): void
    {
        $physical = $this->createAdmin()->getPhysicalIndices();

        $byName = array_column($physical, null, 'name');
        $this->assertSame(['.kibana', 'asset_dev_2025-12-01-000000', 'asset_dev_2026-01-01-000000', 'other_app', 'tag_dev'], array_keys($byName));

        $this->assertSame(ESIndexAdmin::ROLE_SYSTEM, $byName['.kibana']['role']);
        $this->assertNull($byName['.kibana']['logicalIndex']);

        $this->assertSame(ESIndexAdmin::ROLE_ACTIVE, $byName['asset_dev_2026-01-01-000000']['role']);
        $this->assertSame('asset', $byName['asset_dev_2026-01-01-000000']['logicalIndex']);
        $this->assertSame(['asset_dev', 'asset_snapshot'], $byName['asset_dev_2026-01-01-000000']['aliases']);

        $this->assertSame(ESIndexAdmin::ROLE_OLD, $byName['asset_dev_2025-12-01-000000']['role']);
        $this->assertSame('asset', $byName['asset_dev_2025-12-01-000000']['logicalIndex']);

        $this->assertSame(ESIndexAdmin::ROLE_DIRECT, $byName['tag_dev']['role']);
        $this->assertSame('tag', $byName['tag_dev']['logicalIndex']);

        $this->assertSame(ESIndexAdmin::ROLE_UNMANAGED, $byName['other_app']['role']);

        $tag = $byName['tag_dev'];
        $this->assertSame(4, $tag['docsCount']);
        $this->assertSame(2048, $tag['storeSize']);
        $this->assertSame(1, $tag['primaries']);
        $this->assertSame(0, $tag['replicas']);
        $this->assertSame('2026-01-01', $tag['createdAt']->format('Y-m-d'));
        $this->assertSame('green', $tag['health']);
        $this->assertSame('open', $tag['status']);
    }

    public function testLogicalIndicesAggregatePhysicalOnes(): void
    {
        $logical = array_column($this->createAdmin()->getLogicalIndices(), null, 'name');

        $this->assertSame(['asset', 'tag', 'basket'], array_keys($logical));

        $asset = $logical['asset'];
        $this->assertSame('asset_dev', $asset['target']);
        $this->assertTrue($asset['useAlias']);
        $this->assertTrue($asset['exists']);
        $this->assertSame(['asset_dev_2026-01-01-000000'], $asset['activeIndices']);
        $this->assertSame(['asset_dev_2025-12-01-000000'], $asset['oldIndices']);
        $this->assertSame(117, $asset['docsCount'], 'old generations do not count');

        $tag = $logical['tag'];
        $this->assertFalse($tag['useAlias']);
        $this->assertTrue($tag['exists']);
        $this->assertSame(['tag_dev'], $tag['activeIndices']);
        $this->assertSame(4, $tag['docsCount']);

        $basket = $logical['basket'];
        $this->assertFalse($basket['exists']);
        $this->assertSame([], $basket['activeIndices']);
        $this->assertSame([], $basket['oldIndices']);
    }

    public function testAliasesAreGroupedByAliasName(): void
    {
        $this->assertSame([
            'asset_dev' => ['asset_dev_2026-01-01-000000'],
            'asset_snapshot' => ['asset_dev_2026-01-01-000000', 'other_app'],
        ], $this->createAdmin()->getAliases());
    }

    public function testAddAndRemoveAliasSendSingleActions(): void
    {
        $admin = $this->createAdmin();
        $bodies = [];
        $this->indices->expects($this->exactly(2))->method('updateAliases')
            ->willReturnCallback(function (array $params) use (&$bodies) {
                $bodies[] = $params['body']['actions'];

                return $this->createResponse([]);
            });

        $admin->addAlias('idx', 'al');
        $admin->removeAlias('idx', 'al');

        $this->assertSame([
            [['add' => ['index' => 'idx', 'alias' => 'al']]],
            [['remove' => ['index' => 'idx', 'alias' => 'al']]],
        ], $bodies);
    }

    public function testSwitchAliasIsAtomicAndRemovesOtherTargets(): void
    {
        $admin = $this->createAdmin();
        $actions = null;
        $this->indices->expects($this->once())->method('updateAliases')
            ->willReturnCallback(function (array $params) use (&$actions) {
                $actions = $params['body']['actions'];

                return $this->createResponse([]);
            });

        $previous = $admin->switchAlias('asset_snapshot', 'asset_dev_2025-12-01-000000');

        $this->assertSame(['asset_dev_2026-01-01-000000', 'other_app'], $previous);
        $this->assertSame([
            ['remove' => ['index' => 'asset_dev_2026-01-01-000000', 'alias' => 'asset_snapshot']],
            ['remove' => ['index' => 'other_app', 'alias' => 'asset_snapshot']],
            ['add' => ['index' => 'asset_dev_2025-12-01-000000', 'alias' => 'asset_snapshot']],
        ], $actions);
    }

    public function testSwitchAliasToItsCurrentIndexOnlyAdds(): void
    {
        $admin = $this->createAdmin();
        $actions = null;
        $this->indices->method('updateAliases')->willReturnCallback(function (array $params) use (&$actions) {
            $actions = $params['body']['actions'];

            return $this->createResponse([]);
        });

        $previous = $admin->switchAlias('asset_dev', 'asset_dev_2026-01-01-000000');

        $this->assertSame([], $previous);
        $this->assertSame([['add' => ['index' => 'asset_dev_2026-01-01-000000', 'alias' => 'asset_dev']]], $actions);
    }

    public function testIndexLifecycleCallsTheRightEndpoints(): void
    {
        $admin = $this->createAdmin();
        $calls = [];
        foreach (['delete', 'open', 'close', 'refresh'] as $method) {
            $this->indices->expects($this->once())->method($method)
                ->willReturnCallback(function (array $params) use (&$calls, $method) {
                    $calls[] = $method.':'.$params['index'];

                    return $this->createResponse([]);
                });
        }

        $admin->deleteIndex('a');
        $admin->openIndex('b');
        $admin->closeIndex('c');
        $admin->refreshIndex('d');

        $this->assertSame(['delete:a', 'open:b', 'close:c', 'refresh:d'], $calls);
    }

    public function testIndexExists(): void
    {
        $admin = $this->createAdmin();
        $response = $this->createMock(\Elastic\Elasticsearch\Response\Elasticsearch::class);
        $response->method('asBool')->willReturn(true);
        $this->indices->expects($this->once())->method('exists')->with(['index' => 'x'])->willReturn($response);

        $this->assertTrue($admin->indexExists('x'));
    }

    public function testIndexDetailsMergeGetAndStats(): void
    {
        $admin = $this->createAdmin();
        $this->indices->method('get')->with(['index' => 'x'])->willReturn($this->createResponse([
            'x' => ['settings' => ['index' => ['number_of_shards' => '1']], 'mappings' => ['properties' => []], 'aliases' => ['al' => []]],
        ]));
        $this->indices->method('stats')->with(['index' => 'x'])->willReturn($this->createResponse([
            'indices' => ['x' => ['total' => ['docs' => ['count' => 3]]]],
        ]));

        $this->assertSame([
            'settings' => ['index' => ['number_of_shards' => '1']],
            'mappings' => ['properties' => []],
            'aliases' => ['al'],
            'stats' => ['docs' => ['count' => 3]],
        ], $admin->getIndexDetails('x'));
    }

    public function testResetLogicalIndexDelegatesToTheResetter(): void
    {
        $admin = $this->createAdmin();
        $this->resetter->expects($this->once())->method('resetIndex')->with('asset');

        $admin->resetLogicalIndex('asset');
    }

    public function testResetUnknownLogicalIndexThrows(): void
    {
        $admin = $this->createAdmin();
        $this->resetter->expects($this->never())->method('resetIndex');

        $this->expectException(\InvalidArgumentException::class);
        $admin->resetLogicalIndex('asset_dev_2026-01-01-000000');
    }

    public function testRemoveOldIndicesOnlyDeletesOldGenerations(): void
    {
        $admin = $this->createAdmin();
        $this->indices->expects($this->once())->method('delete')
            ->with(['index' => 'asset_dev_2025-12-01-000000'])
            ->willReturn($this->createResponse([]));

        $this->assertSame(['asset_dev_2025-12-01-000000'], $admin->removeOldIndices('asset'));
    }

    public function testRemoveOldIndicesOfUnknownLogicalIndexThrows(): void
    {
        $admin = $this->createAdmin();
        $this->indices->expects($this->never())->method('delete');

        $this->expectException(\InvalidArgumentException::class);
        $admin->removeOldIndices('nope');
    }

    public function testIsLogicalIndex(): void
    {
        $admin = $this->createAdmin();

        $this->assertTrue($admin->isLogicalIndex('asset'));
        $this->assertFalse($admin->isLogicalIndex('asset_dev'));
    }
}
