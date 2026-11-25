<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\EntityListTools;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityListToolsTest extends AbstractToolTestCase
{
    private EntityListTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new EntityListTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(workspaceId: 'w1', name: 'peo');

        $this->assertLastRequest('GET', '/entity-lists', ['workspace' => 'w1', 'name' => 'peo', 'page' => '1']);
    }

    public function testCrud(): void
    {
        $this->kernel->willRespond(['id' => 'l1'], 201);
        $this->tools->create('w1', 'People', allowNewValues: true, withSynonyms: false);
        $this->assertLastRequest('POST', '/entity-lists', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'People',
            'allowNewValues' => true,
            'withSynonyms' => false,
        ]);

        $this->kernel->willRespond(['id' => 'l1']);
        $this->tools->get('l1');
        $this->assertLastRequest('GET', '/entity-lists/l1');

        $this->kernel->willRespond(['id' => 'l1']);
        $this->tools->update('l1', name: 'Persons');
        $this->assertLastRequest('PATCH', '/entity-lists/l1', body: ['name' => 'Persons']);

        $this->kernel->willRespond(null, 204);
        $this->tools->delete('l1');
        $this->assertLastRequest('DELETE', '/entity-lists/l1');
    }

    public function testImport(): void
    {
        $this->kernel->willRespond(['id' => 'l1']);

        $this->tools->import('l1', 'raw', "Paris\nLyon");

        $this->assertLastRequest('POST', '/entity-lists/l1/import', body: ['format' => 'raw', 'data' => "Paris\nLyon"]);
    }

    public function testExportReturnsTheStreamedFile(): void
    {
        $this->kernel->willRespondWith(new StreamedResponse(static function (): void {
            echo "id,value\n";
            echo "e1,Paris\n";
        }, 200, ['Content-Type' => 'text/csv']));

        $this->assertSame(
            ['format' => 'csv', 'content' => "id,value\ne1,Paris\n"],
            $this->tools->export('l1', locale: 'fr'),
        );
        $this->assertLastRequest('POST', '/entity-lists/l1/export', body: ['format' => 'csv', 'locale' => 'fr']);
    }

    public function testClear(): void
    {
        $this->kernel->willRespond(['id' => 'l1']);

        $this->assertSame(['cleared' => 'l1'], $this->tools->clear('l1'));
        $this->assertLastRequest('POST', '/entity-lists/l1/clear', body: []);
    }
}
