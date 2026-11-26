<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\AssetInsightTools;
use Mcp\Exception\ToolCallException;

class AssetInsightToolsTest extends AbstractToolTestCase
{
    private AssetInsightTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AssetInsightTools($this->client);
    }

    public function testListFileVersions(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listFileVersions('a1');

        $this->assertLastRequest('GET', '/asset-file-versions', ['assetId' => 'a1', 'page' => '1']);
    }

    public function testFindDuplicates(): void
    {
        $this->kernel->willRespond(['duplicates' => [['asset' => ['id' => 'a2'], 'analyzers' => ['checksum']]]]);

        $this->assertSame(
            ['duplicates' => [['asset' => ['id' => 'a2'], 'analyzers' => ['checksum']]]],
            $this->tools->findDuplicates('a1'),
        );
        $this->assertLastRequest('GET', '/assets/a1/duplicates');
    }

    public function testGetFileMetadata(): void
    {
        $this->kernel->willRespond(['id' => 'f1', 'metadata' => ['XMP-dc:Title' => ['value' => 'Sunset']]]);

        $this->assertSame(['XMP-dc:Title' => ['value' => 'Sunset']], $this->tools->getFileMetadata('f1')['metadata']);
        $this->assertLastRequest('GET', '/files/f1/metadata');
    }

    public function testGetMetrics(): void
    {
        $this->kernel->willRespond(['nb_impressions' => 12]);

        $this->assertSame(['nb_impressions' => 12], $this->tools->getMetrics('a1'));
        $this->assertLastRequest('GET', '/assets/a1/metrics');
    }

    public function testGetMetricsWithoutMatomo(): void
    {
        $this->kernel->willRespond(['detail' => 'Matomo URL is not configured'], 500);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('API responded 500');

        $this->tools->getMetrics('a1');
    }

    public function testListAuditLogsWithDefaults(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listAuditLogs();

        $this->assertLastRequest('GET', '/audit-logs', ['page' => '1', 'limit' => '50']);
    }

    public function testListAuditLogsWithFilters(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listAuditLogs(
            objectId: 'a1',
            objectType: 'asset',
            actions: [2 => 'asset_moved', 4 => 'object_trashed'],
            userId: 'u1',
            workspaceId: 'w1',
            after: '2026-01-01T00:00:00Z',
            page: 3,
            limit: 100,
        );

        $this->assertLastRequest('GET', '/audit-logs', [
            'objectId' => 'a1',
            'objectType' => 'asset',
            'action' => ['asset_moved', 'object_trashed'],
            'userId' => 'u1',
            'workspaceId' => 'w1',
            'date' => ['after' => '2026-01-01T00:00:00Z'],
            'page' => '3',
            'limit' => '100',
        ]);
    }
}
