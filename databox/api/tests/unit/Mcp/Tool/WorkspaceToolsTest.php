<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\WorkspaceTools;

class WorkspaceToolsTest extends AbstractToolTestCase
{
    private WorkspaceTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new WorkspaceTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(query: 'foo', page: 2);

        $this->assertLastRequest('GET', '/workspaces', [
            'query' => 'foo',
            'page' => '2',
            'limit' => '30',
        ]);
    }

    public function testGetById(): void
    {
        $this->kernel->willRespond(['id' => 'x']);

        $this->tools->get('0190c3b4-5f2a-7c3e-9d1a-2b3c4d5e6f70');

        $this->assertLastRequest('GET', '/workspaces/0190c3b4-5f2a-7c3e-9d1a-2b3c4d5e6f70');
    }

    public function testGetBySlug(): void
    {
        $this->kernel->willRespond(['id' => 'x']);

        $this->tools->get('my ws');

        $this->assertSame('/workspaces-by-slug/my%20ws', $this->kernel->lastRequest()->getRequestUri());
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'x'], 201);

        $this->tools->create('Photos', 'photos', enabledLocales: ['en', 'fr'], assetDefaultStatus: 1);

        $this->assertLastRequest('POST', '/workspaces', body: [
            'name' => 'Photos',
            'slug' => 'photos',
            'enabledLocales' => ['en', 'fr'],
            'assetDefaultStatus' => 1,
        ]);
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'x']);

        $this->tools->update('w1', trashRetentionDelay: 0, fileAnalysisRequired: false, terms: '');

        $this->assertLastRequest('PATCH', '/workspaces/w1', body: [
            'trashRetentionDelay' => 0,
            'fileAnalysisRequired' => false,
            'terms' => '',
        ]);
        $this->assertSame('application/merge-patch+json', $this->kernel->lastRequest()->headers->get('Content-Type'));
    }
}
