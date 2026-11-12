<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\ProfileTools;

class ProfileToolsTest extends AbstractToolTestCase
{
    private ProfileTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new ProfileTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list();

        $this->assertLastRequest('GET', '/publication-profiles', [
            'order' => ['name' => 'asc'],
            'page' => '1',
        ]);
    }

    public function testListByName(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('foo', 2);

        $this->assertLastRequest('GET', '/publication-profiles', [
            'name' => 'foo',
            'order' => ['name' => 'asc'],
            'page' => '2',
        ]);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'pr1']);

        $this->assertSame(['id' => 'pr1'], $this->tools->get('pr1'));

        $this->assertLastRequest('GET', '/publication-profiles/pr1');
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'pr1'], 201);

        $this->tools->create('Foo', ['downloadEnabled' => true]);

        $this->assertLastRequest('POST', '/publication-profiles', body: [
            'name' => 'Foo',
            'config' => ['downloadEnabled' => true],
        ]);
    }

    public function testCreateWithoutConfig(): void
    {
        $this->kernel->willRespond(['id' => 'pr1'], 201);

        $this->tools->create('Foo');

        $this->assertLastRequest('POST', '/publication-profiles', body: ['name' => 'Foo']);
    }

    public function testUpdateSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'pr1']);

        $this->tools->update('pr1', config: ['layout' => 'grid']);

        $this->assertLastRequest('PATCH', '/publication-profiles/pr1', body: ['config' => ['layout' => 'grid']]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'pr1'], $this->tools->delete('pr1'));

        $this->assertLastRequest('DELETE', '/publication-profiles/pr1');
    }
}
