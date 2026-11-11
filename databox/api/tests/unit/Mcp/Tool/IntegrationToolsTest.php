<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\IntegrationTools;

class IntegrationToolsTest extends AbstractToolTestCase
{
    private IntegrationTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new IntegrationTools($this->client);
    }

    public function testListTypesLeavesTheReferencesOut(): void
    {
        $this->kernel->willRespond([
            'member' => [['id' => 'core--blurhash', 'name' => 'core.blurhash', 'reference' => '…', 'references' => [], 'schema' => []]],
            'totalItems' => 1,
        ]);

        $result = $this->tools->listTypes();

        $this->assertSame([['id' => 'core--blurhash', 'name' => 'core.blurhash']], $result['items']);
        $this->assertLastRequest('GET', '/integration-types');
    }

    public function testList(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->list(workspaceId: 'w1', enabled: true);

        $this->assertLastRequest('GET', '/integrations', ['workspace' => 'w1', 'enabled' => 'true', 'page' => '1']);
    }

    public function testCreate(): void
    {
        $this->kernel->willRespond(['id' => 'i1'], 201);

        $this->tools->create('core.blurhash', workspaceId: 'w1', configYaml: 'rendition: thumbnail', needs: ['i0']);

        $this->assertLastRequest('POST', '/integrations', body: [
            'integration' => 'core.blurhash',
            'workspace' => '/workspaces/w1',
            'configYaml' => 'rendition: thumbnail',
            'needs' => ['/integrations/i0'],
        ]);
    }

    public function testUpdateResendsTheIntegrationType(): void
    {
        $this->kernel
            ->willRespond(['id' => 'i1', 'integration' => 'core.blurhash'])
            ->willRespond(['id' => 'i1']);

        $this->tools->update('i1', enabled: false);

        $this->assertSame('GET', $this->kernel->calls[0]['request']->getMethod());
        $this->assertLastRequest('PATCH', '/integrations/i1', body: [
            'integration' => 'core.blurhash',
            'enabled' => false,
        ]);
    }

    public function testDelete(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'i1'], $this->tools->delete('i1'));
        $this->assertLastRequest('DELETE', '/integrations/i1');
    }
}
