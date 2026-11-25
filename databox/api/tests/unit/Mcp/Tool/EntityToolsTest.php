<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\EntityTools;

class EntityToolsTest extends AbstractToolTestCase
{
    private EntityTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new EntityTools($this->client);
    }

    public function testListWithDefaults(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list();

        $this->assertLastRequest('GET', '/attribute-entities', ['page' => '1', 'limit' => '30', 'order' => ['value' => 'asc']]);
    }

    public function testListWithFilters(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(listId: 'l1', workspaceId: 'w1', query: 'par', value: 'Paris', status: 0, withUsage: true, order: ['createdAt' => 'desc'], page: 2, limit: 50);

        $this->assertLastRequest('GET', '/attribute-entities', [
            'list' => 'l1',
            'workspace' => 'w1',
            'query' => 'par',
            'value' => 'Paris',
            'status' => '0',
            'usage' => 'true',
            'page' => '2',
            'limit' => '50',
            'order' => ['createdAt' => 'desc'],
        ]);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['@id' => '/attribute-entities/e1', 'id' => 'e1', 'value' => 'Paris']);

        $this->assertSame(['id' => 'e1', 'value' => 'Paris'], $this->tools->get('e1'));
        $this->assertLastRequest('GET', '/attribute-entities/e1');
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'e1'], 201);

        $this->tools->create(
            'l1',
            'Eiffel Tower',
            translations: ['fr' => 'Tour Eiffel'],
            synonyms: ['fr' => ['Dame de fer']],
            emoji: '🗼',
            externalId: 'Q243',
        );

        $this->assertLastRequest('POST', '/attribute-entities', body: [
            'list' => '/entity-lists/l1',
            'value' => 'Eiffel Tower',
            'translations' => ['fr' => 'Tour Eiffel'],
            'synonyms' => ['fr' => ['Dame de fer']],
            'emoji' => '🗼',
            'externalId' => 'Q243',
        ]);
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'e1']);

        $this->tools->update('e1', value: 'Paris', status: 2);

        $this->assertLastRequest('PATCH', '/attribute-entities/e1', body: ['value' => 'Paris', 'status' => 2]);
    }

    public function testUpdateRemovesTheEmojiAndTheColor(): void
    {
        $this->kernel->willRespond(['id' => 'e1']);

        $this->tools->update('e1', emoji: '', color: '#ff8800', note: '');

        $this->assertLastRequest('PATCH', '/attribute-entities/e1', body: ['note' => '', 'emoji' => null, 'color' => '#ff8800']);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'e1'], $this->tools->delete('e1'));
        $this->assertLastRequest('DELETE', '/attribute-entities/e1');
    }

    public function testMergeLeavesTheKeptEntityOut(): void
    {
        $this->kernel->willRespond(['id' => 'e1']);

        $this->tools->merge('e1', [3 => 'e2', 4 => 'e1', 7 => 'e3']);

        $this->assertLastRequest('PUT', '/attribute-entities/e1/merge', body: ['ids' => ['e2', 'e3']]);
    }
}
