<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\QuarantineTools;

class QuarantineToolsTest extends AbstractToolTestCase
{
    private QuarantineTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new QuarantineTools($this->client);
    }

    public function testCheck(): void
    {
        $this->kernel->willRespond(['items' => [['id' => 'a1', 'bypass' => true, 'reasons' => []]]]);

        $result = $this->tools->check([3 => 'a1']);

        $this->assertSame(['items' => [['id' => 'a1', 'bypass' => true, 'reasons' => []]]], $result);
        $this->assertLastRequest('POST', '/assets/quarantine/prepare-batch', body: ['ids' => ['a1']]);
    }

    public function testResolve(): void
    {
        $this->kernel->willRespond(['processed' => 1, 'skipped' => 1, 'failed' => 0, 'results' => []]);

        $this->tools->resolve('merge', [['id' => 'a1', 'targetId' => 'a9'], ['id' => 'a2', 'extra' => 'x']], addAsVersion: false);

        $this->assertLastRequest('POST', '/assets/quarantine/batch', body: [
            'action' => 'merge',
            'items' => [['id' => 'a1', 'targetId' => 'a9'], ['id' => 'a2']],
            'addAsVersion' => false,
        ]);
    }

    public function testBypass(): void
    {
        $this->kernel->willRespond(['id' => 'a1', 'status' => 0]);

        $this->tools->bypass('a1');

        $this->assertLastRequest('POST', '/assets/a1/quarantine-bypass', body: []);
    }
}
