<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\WorkflowTools;
use Mcp\Exception\ToolCallException;

class WorkflowToolsTest extends AbstractToolTestCase
{
    private WorkflowTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new WorkflowTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(assetIds: [2 => 'a1', 5 => 'a2'], status: 2);

        $this->assertLastRequest('GET', '/workflows', ['asset' => ['a1', 'a2'], 'status' => '2', 'page' => '1']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'w1', 'stages' => []]);

        $this->assertSame(['id' => 'w1', 'stages' => []], $this->tools->get('w1'));
        $this->assertLastRequest('GET', '/workflows/w1');
    }

    public function testRerunJob(): void
    {
        $this->kernel->willRespond(['id' => 'w1']);

        $this->tools->rerunJob('w1', 'core.rendition:build');

        $this->assertLastRequest('POST', '/workflows/w1/jobs/core.rendition%3Abuild/rerun', body: []);
    }

    public function testCancel(): void
    {
        $this->kernel->willRespond(['id' => 'w1', 'status' => 3]);

        $this->assertSame(['id' => 'w1', 'status' => 3], $this->tools->cancel('w1'));
        $this->assertLastRequest('POST', '/workflows/w1/cancel', body: []);
    }

    public function testReprocessAsset(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->reprocessAsset('a1');

        $this->assertLastRequest('PUT', '/assets/a1/trigger-workflow', body: []);
    }

    public function testReprocessAssetReportsARunningWorkflow(): void
    {
        $this->kernel->willRespond(['detail' => 'A workflow is already running on this asset, cancel it to run a new one'], 409);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('API responded 409: A workflow is already running');

        $this->tools->reprocessAsset('a1');
    }
}
