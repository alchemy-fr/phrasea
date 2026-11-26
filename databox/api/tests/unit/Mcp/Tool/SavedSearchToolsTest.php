<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\SavedSearchTools;

class SavedSearchToolsTest extends AbstractToolTestCase
{
    private SavedSearchTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new SavedSearchTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(query: 'report', page: 2, limit: 10);

        $this->assertLastRequest('GET', '/saved-searches', ['query' => 'report', 'page' => '2', 'limit' => '10']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 's1', 'name' => 'Reports']);

        $this->assertSame(['id' => 's1', 'name' => 'Reports'], $this->tools->get('s1'));
        $this->assertLastRequest('GET', '/saved-searches/s1');
    }

    public function testCreateStoresTheCriteriaInTheFormatOfTheClient(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->create(
            'Reports',
            query: 'annual',
            conditions: [3 => 'title STARTS WITH "Report"', 7 => '@type IS "image"'],
            order: ['@createdAt' => 'desc', 'title' => 'asc'],
            privacy: 2,
        );

        $this->assertLastRequest('POST', '/saved-searches', body: [
            'name' => 'Reports',
            'privacy' => 2,
            'data' => [
                'query' => 'annual',
                'conditions' => [
                    ['id' => 'c1', 'query' => 'title STARTS WITH "Report"'],
                    ['id' => 'c2', 'query' => '@type IS "image"'],
                ],
                'sortBy' => [
                    ['a' => '@createdAt', 'w' => 1, 'g' => false],
                    ['a' => 'title', 'w' => 0, 'g' => false],
                ],
            ],
        ]);
    }

    public function testCreateWithoutCriteria(): void
    {
        $this->kernel->willRespond(['id' => 's1'], 201);

        $this->tools->create('Everything');

        $this->assertLastRequest('POST', '/saved-searches', body: [
            'name' => 'Everything',
            'data' => ['query' => '', 'conditions' => [], 'sortBy' => []],
        ]);
    }

    public function testUpdateKeepsTheCriteriaWhenNoneIsGiven(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->tools->update('s1', name: 'Renamed');

        $this->assertLastRequest('PATCH', '/saved-searches/s1', body: ['name' => 'Renamed']);
    }

    public function testUpdateReplacesTheCriteria(): void
    {
        $this->kernel->willRespond(['id' => 's1']);

        $this->tools->update('s1', conditions: ['@type IS "video"']);

        $this->assertLastRequest('PATCH', '/saved-searches/s1', body: [
            'data' => ['query' => '', 'conditions' => [['id' => 'c1', 'query' => '@type IS "video"']], 'sortBy' => []],
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 's1'], $this->tools->delete('s1'));
        $this->assertLastRequest('DELETE', '/saved-searches/s1');
    }

    public function testListAutomations(): void
    {
        $this->kernel->willRespond(['member' => [['id' => 'au1']], 'totalItems' => 1]);

        $result = $this->tools->listAutomations('s1');

        $this->assertSame([['id' => 'au1']], $result['items']);
        $this->assertLastRequest('GET', '/saved-search-automations', ['savedSearch' => 's1']);
    }

    public function testRunAutomation(): void
    {
        $this->kernel->willRespond(['id' => 'r1', 'status' => 'pending'], 201);

        $this->assertSame(['id' => 'r1', 'status' => 'pending'], $this->tools->runAutomation('au1', dryRun: true));
        $this->assertLastRequest('POST', '/saved-search-automations/au1/run', body: ['dryRun' => true]);
    }

    public function testListRuns(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listRuns('au1');

        $this->assertLastRequest('GET', '/automation-runs', ['automation' => 'au1', 'page' => '1']);
    }
}
