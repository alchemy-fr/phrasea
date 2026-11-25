<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\SubDefinitionTools;

class SubDefinitionToolsTest extends AbstractToolTestCase
{
    private SubDefinitionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new SubDefinitionTools($this->client);
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list('a1', 2);

        $this->assertLastRequest('GET', '/assets/a1/sub-definitions', ['page' => '2']);
    }

    public function testGet(): void
    {
        $this->kernel->willRespond(['id' => 'sd1']);

        $this->assertSame(['id' => 'sd1'], $this->tools->get('sd1'));

        $this->assertLastRequest('GET', '/sub-definitions/sd1');
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'sd1'], $this->tools->delete('sd1'));

        $this->assertLastRequest('DELETE', '/sub-definitions/sd1');
    }
}
