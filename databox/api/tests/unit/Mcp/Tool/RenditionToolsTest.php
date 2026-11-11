<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Tool;

use Alchemy\McpBundle\Test\AbstractToolTestCase;
use App\Mcp\Tool\RenditionTools;
use Mcp\Exception\ToolCallException;

class RenditionToolsTest extends AbstractToolTestCase
{
    private RenditionTools $tools;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tools = new RenditionTools($this->client);
    }

    public function testGetBuildReference(): void
    {
        $this->kernel->willRespond(['@id' => '/rendition-build-reference', 'reference' => 'r', 'references' => []]);

        $this->assertSame(['reference' => 'r', 'references' => []], $this->tools->getBuildReference());
        $this->assertLastRequest('GET', '/rendition-build-reference');
    }

    public function testListDefinitions(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listDefinitions(workspaceId: 'w1', target: 2);

        $this->assertLastRequest('GET', '/rendition-definitions', [
            'workspaceId' => 'w1',
            'target' => '2',
            'page' => '1',
            'limit' => '30',
        ]);
    }

    public function testCreateDefinitionMapsRelationsToIris(): void
    {
        $this->kernel->willRespond(['id' => 'd1'], 201);

        $this->tools->createDefinition(
            workspaceId: 'w1',
            name: 'Thumb',
            policyId: 'p1',
            buildMode: 2,
            definition: "image:\n    transformations: []\n",
            parentId: 'd0',
            useAsThumbnail: true,
        );

        $this->assertLastRequest('POST', '/rendition-definitions', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Thumb',
            'policy' => '/rendition-policies/p1',
            'buildMode' => 2,
            'definition' => "image:\n    transformations: []\n",
            'parent' => '/rendition-definitions/d0',
            'useAsThumbnail' => true,
        ]);
    }

    public function testUpdateDefinitionKeepsTheParentWhenUnset(): void
    {
        $this->kernel->willRespond(['id' => 'd1']);

        $this->tools->updateDefinition('d1', definition: 'x: y');

        $this->assertLastRequest('PATCH', '/rendition-definitions/d1', body: ['definition' => 'x: y']);
    }

    public function testUpdateDefinitionRemovesTheParent(): void
    {
        $this->kernel->willRespond(['id' => 'd1']);

        $this->tools->updateDefinition('d1', parentId: '');

        $this->assertLastRequest('PATCH', '/rendition-definitions/d1', body: ['parent' => null]);
    }

    public function testUpdateDefinitionReportsTheInvalidBuildConfiguration(): void
    {
        $this->kernel->willRespond([
            'violations' => [['propertyPath' => 'definition', 'message' => 'Invalid rendition definition: unknown module "foo"']],
        ], 422);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('API responded 422: definition: Invalid rendition definition: unknown module "foo"');

        $this->tools->updateDefinition('d1', definition: 'image: {transformations: [{module: foo}]}');
    }

    public function testDuplicateDefinition(): void
    {
        $this->kernel->willRespond(['id' => 'd2'], 201);

        $this->tools->duplicateDefinition('d1');

        $this->assertLastRequest('POST', '/rendition-definitions/d1/duplicate', body: []);
    }

    public function testSortDefinitionsSendsTheIds(): void
    {
        $this->kernel->willRespond(null, 200);

        $this->assertSame(['sorted' => ['b', 'a']], $this->tools->sortDefinitions([3 => 'b', 5 => 'a']));

        $this->assertLastRequest('POST', '/rendition-definitions/sort', body: ['b', 'a']);
    }

    public function testDeleteDefinition(): void
    {
        $this->kernel->willRespond(null, 204);

        $this->assertSame(['deleted' => 'd1'], $this->tools->deleteDefinition('d1'));
        $this->assertLastRequest('DELETE', '/rendition-definitions/d1');
    }

    public function testPolicies(): void
    {
        $this->kernel->willRespond(self::emptyCollection());
        $this->tools->listPolicies('w1');
        $this->assertLastRequest('GET', '/rendition-policies', ['workspaceId' => 'w1', 'page' => '1']);

        $this->kernel->willRespond(['id' => 'p1'], 201);
        $this->tools->createPolicy('w1', 'Restricted', public: false, editable: false);
        $this->assertLastRequest('POST', '/rendition-policies', body: [
            'workspace' => '/workspaces/w1',
            'name' => 'Restricted',
            'public' => false,
            'editable' => false,
        ]);

        $this->kernel->willRespond(['id' => 'p1']);
        $this->tools->updatePolicy('p1', public: true);
        $this->assertLastRequest('PATCH', '/rendition-policies/p1', body: ['public' => true]);

        $this->kernel->willRespond(null, 204);
        $this->tools->deletePolicy('p1');
        $this->assertLastRequest('DELETE', '/rendition-policies/p1');
    }

    public function testListAssetRenditions(): void
    {
        $this->kernel->willRespond(self::emptyCollection());

        $this->tools->listAssetRenditions('a1');

        $this->assertLastRequest('GET', '/renditions', ['assetId' => 'a1']);
    }
}
