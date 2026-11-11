<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\AttributeDefinitionTools;

class AttributeDefinitionToolsTest extends AbstractToolTestCase
{
    private AttributeDefinitionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new AttributeDefinitionTools($this->client);
    }

    public function testListDefinitions(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listDefinitions(workspaceId: 'w1', type: 'entity', enabled: false);

        $this->assertLastRequest('GET', '/attribute-definitions', [
            'workspaceId' => 'w1',
            'type' => 'entity',
            'enabled' => 'false',
            'page' => '1',
            'limit' => '100',
        ]);
    }

    public function testCreateDefinition(): void
    {
        $this->kernel->willRespond(['id' => 'a1'], 201);

        $this->tools->createDefinition(
            workspaceId: 'w1',
            name: 'Keywords',
            policyId: 'p1',
            type: 'entity',
            entityListId: 'l1',
            multiple: true,
            initialValues: ['_' => '{{ file.filename }}'],
            readFromMetadata: ['IPTC:Keywords'],
        );

        $this->assertLastRequest('POST', '/attribute-definitions', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Keywords',
            'policy' => '/attribute-policies/p1',
            'type' => 'entity',
            'entityList' => '/entity-lists/l1',
            'multiple' => true,
            'initialValues' => ['_' => '{{ file.filename }}'],
            'readFromMetadata' => ['IPTC:Keywords'],
        ]);
    }

    public function testUpdateDefinitionSendsOnlyGivenFields(): void
    {
        $this->kernel->willRespond(['id' => 'a1']);

        $this->tools->updateDefinition('a1', allowInvalid: false, policyId: 'p2');

        $this->assertLastRequest('PATCH', '/attribute-definitions/a1', body: [
            'policy' => '/attribute-policies/p2',
            'allowInvalid' => false,
        ]);
    }

    public function testSortAndDeleteDefinitions(): void
    {
        $this->kernel->willRespond(null, 204);
        $this->assertSame(['sorted' => ['a2', 'a1']], $this->tools->sortDefinitions(['a2', 'a1']));
        $this->assertLastRequest('POST', '/attribute-definitions/sort', body: ['a2', 'a1']);

        $this->kernel->willRespond(null, 204);
        $this->tools->deleteDefinition('a1');
        $this->assertLastRequest('DELETE', '/attribute-definitions/a1');
    }

    public function testPolicies(): void
    {
        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->listPolicies('w1', page: 2);
        $this->assertLastRequest('GET', '/attribute-policies', ['workspaceId' => 'w1', 'page' => '2']);

        $this->kernel->willRespond(['id' => 'p1'], 201);
        $this->tools->createPolicy('w1', 'Rights', public: true, editable: false, key: 'rights');
        $this->assertLastRequest('POST', '/attribute-policies', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Rights',
            'public' => true,
            'editable' => false,
            'key' => 'rights',
        ]);

        $this->kernel->willRespond(['id' => 'p1']);
        $this->tools->updatePolicy('p1', name: 'Renamed');
        $this->assertLastRequest('PATCH', '/attribute-policies/p1', body: ['name' => 'Renamed']);

        $this->kernel->willRespond(null, 204);
        $this->assertSame(['deleted' => 'p1'], $this->tools->deletePolicy('p1'));
        $this->assertLastRequest('DELETE', '/attribute-policies/p1');
    }
}
