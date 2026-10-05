<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\UserSpace;

use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Template\AssetDataTemplate;
use App\Entity\Template\TemplateAttribute;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * Asset data templates (item operations) and template attributes.
 * Listing is covered by AssetDataTemplateListTest.
 */
final class AssetDataTemplateApiTest extends AbstractDataboxTestCase
{
    use UserSpaceTestTrait;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = $this->createOtherWorkspace(self::USER, 'templates');
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());
    }

    public function testCreate(): void
    {
        $definition = $this->createDefinition('Title');
        $tag = $this->findOrCreateTagByName('foo', $this->workspace);
        $collection = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::USER, 'name' => 'Coll']);

        $data = $this->apiJson('POST', '/asset-data-templates', self::USER, [
            'name' => 'My template',
            'workspace' => '/workspaces/'.$this->workspace->getId(),
            'collection' => '/collections/'.$collection->getId(),
            'includeCollectionChildren' => true,
            'tags' => ['/tags/'.$tag->getId()],
            'privacy' => 2,
            'public' => true,
            'ownerId' => self::OTHER,
            'attributes' => [
                ['definitionId' => $definition->getId(), 'value' => 'Hello'],
            ],
        ]);

        $this->assertSame('asset-data-template', $data['@type']);
        $this->assertSame('My template', $data['name']);
        $this->assertSame(2, $data['privacy']);
        $this->assertSame(['edit' => true, 'delete' => true, 'editPermissions' => true], $data['capabilities']);

        $em = self::getEntityManager();
        $em->clear();
        $template = $em->find(AssetDataTemplate::class, $data['id']);
        $this->assertSame(self::USER, $template->getOwnerId());
        $this->assertSame($this->workspace->getId(), $template->getWorkspaceId());
        $this->assertSame($collection->getId(), $template->getCollectionId());
        $this->assertTrue($template->isIncludeCollectionChildren());
        $this->assertTrue($template->isPublic());
        $this->assertSame([$tag->getId()], array_map(static fn ($t): string => $t->getId(), $template->getTags()->getValues()));
        $this->assertCount(1, $template->getAttributes());
        $this->assertSame('Hello', $template->getAttributes()->first()->getValue());
    }

    public function testCreateMinimal(): void
    {
        $data = $this->apiJson('POST', '/asset-data-templates', self::USER, [
            'name' => 'Minimal',
            'workspace' => '/workspaces/'.$this->workspace->getId(),
        ]);

        $this->assertSame('Minimal', $data['name']);
        // POST answers with the list group only
        $this->assertArrayNotHasKey('attributes', $data);

        $data = $this->apiJson('GET', '/asset-data-templates/'.$data['id'], self::USER);
        $this->assertFalse($data['public']);
        $this->assertSame([], $data['tags']);
        $this->assertSame([], $data['attributes']);
        $this->assertFalse($data['includeCollectionChildren']);
        $this->assertSame(self::USER, $data['ownerId']);
    }

    public function testCreateRequiresWorkspaceAndName(): void
    {
        $this->assertStatus(400, 'POST', '/asset-data-templates', self::USER, ['name' => 'No workspace']);
        $this->assertStatus(422, 'POST', '/asset-data-templates', self::USER, ['workspace' => '/workspaces/'.$this->workspace->getId()]);
        $this->assertSame(0, self::getEntityManager()->getRepository(AssetDataTemplate::class)->count([]));
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->markTestIncomplete('BUG: anonymous POST /asset-data-templates answers 400 "You must provide ownerId" instead of 401: the Post operation has no `security` (only securityPostDenormalize), see src/Entity/Template/AssetDataTemplate.php:61');

        $this->assertStatus(401, 'POST', '/asset-data-templates', null, [
            'name' => 'Anon',
            'workspace' => '/workspaces/'.$this->workspace->getId(),
        ]);
    }

    public function testCreateRejectsItemsOfAnotherWorkspace(): void
    {
        $foreign = $this->createOtherWorkspace(self::USER, 'foreign');
        $foreignTag = $this->findOrCreateTagByName('bar', $foreign);
        $foreignCollection = $this->createCollection(['workspace' => $foreign, 'ownerId' => self::USER]);
        $foreignDefinition = $this->createDefinition('Foreign', $foreign);

        $base = ['name' => 'T', 'workspace' => '/workspaces/'.$this->workspace->getId()];

        $this->assertStatus(422, 'POST', '/asset-data-templates', self::USER, $base + ['tags' => ['/tags/'.$foreignTag->getId()]]);
        $this->assertStatus(422, 'POST', '/asset-data-templates', self::USER, $base + ['collection' => '/collections/'.$foreignCollection->getId()]);
        $this->assertStatus(400, 'POST', '/asset-data-templates', self::USER, $base + ['attributes' => [
            ['definitionId' => $foreignDefinition->getId(), 'value' => 'x'],
        ]]);
    }

    public function testCreateInUnreadableWorkspaceIsForbidden(): void
    {
        $foreign = $this->createOtherWorkspace('stranger', 'stranger-ws');

        $this->assertStatus(403, 'POST', '/asset-data-templates', self::USER, [
            'name' => 'Intruder',
            'workspace' => '/workspaces/'.$foreign->getId(),
        ]);
    }

    /**
     * @dataProvider getReadMatrix
     */
    public function testReadMatrix(bool $public, ?string $userId, int $expectedStatus): void
    {
        $template = $this->createTemplate('T', self::USER, public: $public);

        $this->assertStatus($expectedStatus, 'GET', '/asset-data-templates/'.$template->getId(), $userId);
    }

    public static function getReadMatrix(): array
    {
        return [
            'private / owner' => [false, self::USER, 200],
            'private / other' => [false, self::OTHER, 403],
            'private / anonymous' => [false, null, 401],
            'private / admin' => [false, self::ADMIN, 200],
            'public / other' => [true, self::OTHER, 200],
        ];
    }

    public function testPrivateTemplateSharedThroughAcl(): void
    {
        $template = $this->createTemplate('T', self::USER);
        $this->grantUserOnObject(self::OTHER, $template, PermissionInterface::VIEW);

        $data = $this->apiJson('GET', '/asset-data-templates/'.$template->getId(), self::OTHER);
        $this->assertSame(['edit' => false, 'delete' => false, 'editPermissions' => false], $data['capabilities']);
        $this->assertSame(self::USER, $data['ownerId']);
    }

    public function testAttributesOfNonPublicPoliciesAreHidden(): void
    {
        $visible = $this->createDefinition('Visible');
        $hidden = $this->createDefinition('Hidden', policyPublic: false);
        $template = $this->createTemplate('T', self::USER, public: true, attributes: [
            [$visible, 'shown'],
            [$hidden, 'secret'],
        ]);

        $data = $this->apiJson('GET', '/asset-data-templates/'.$template->getId(), self::OTHER);
        $this->assertSame(['shown'], array_column(array_values($data['attributes']), 'value'));

        // Admin is granted everything
        $data = $this->apiJson('GET', '/asset-data-templates/'.$template->getId(), self::ADMIN);
        $this->assertCount(2, $data['attributes']);
    }

    public function testUpdate(): void
    {
        $definition = $this->createDefinition('Title');
        $template = $this->createTemplate('T', self::USER, attributes: [[$definition, 'old']]);
        $uri = '/asset-data-templates/'.$template->getId();

        $data = $this->apiJson('PUT', $uri, self::USER, ['name' => 'Renamed']);
        $this->assertSame('Renamed', $data['name']);
        // Attributes are kept when omitted
        $this->assertSame(['old'], array_column(array_values($data['attributes']), 'value'));
    }

    public function testUpdateReplacesAttributes(): void
    {
        $this->markTestIncomplete('BUG: a PUT with "attributes" appends the new attributes to the old ones instead of replacing them: the collection is cleared but the relation has no orphanRemoval, so old TemplateAttribute rows survive, see src/Api/InputTransformer/AssetDataTemplateInputTransformer.php:50 and src/Entity/Template/AssetDataTemplate.php:109');

        $definition = $this->createDefinition('Title');
        $template = $this->createTemplate('T', self::USER, attributes: [[$definition, 'old']]);

        $data = $this->apiJson('PUT', '/asset-data-templates/'.$template->getId(), self::USER, ['attributes' => [
            ['definitionId' => $definition->getId(), 'value' => 'new'],
        ]]);
        $this->assertSame(['new'], array_column(array_values($data['attributes']), 'value'));

        self::getEntityManager()->clear();
        $this->assertSame(1, self::getEntityManager()->getRepository(TemplateAttribute::class)->count([]));
    }

    public function testUpdateKeepsIncludeCollectionChildrenWhenOmitted(): void
    {
        $this->markTestIncomplete('BUG: a PUT without "includeCollectionChildren" resets it to false (the input property defaults to false and is always applied), see src/Api/InputTransformer/AssetDataTemplateInputTransformer.php:69');

        $collection = $this->createCollection(['workspace' => $this->workspace, 'ownerId' => self::USER]);
        $template = $this->createTemplate('T', self::USER, collection: $collection, includeChildren: true);

        $this->apiJson('PUT', '/asset-data-templates/'.$template->getId(), self::USER, ['name' => 'Renamed']);

        self::getEntityManager()->clear();
        $this->assertTrue(self::getEntityManager()->find(AssetDataTemplate::class, $template->getId())->isIncludeCollectionChildren());
    }

    public function testUpdateAccessMatrix(): void
    {
        $template = $this->createTemplate('T', self::USER, public: true);
        $uri = '/asset-data-templates/'.$template->getId();

        // Public does not mean editable
        $this->assertStatus(403, 'PUT', $uri, self::OTHER, ['name' => 'Hijack']);
        $this->assertStatus(401, 'PUT', $uri, null, ['name' => 'Hijack']);

        $this->grantUserOnObject(self::OTHER, $template, PermissionInterface::EDIT);
        $this->assertSame('By other', $this->apiJson('PUT', $uri, self::OTHER, ['name' => 'By other'])['name']);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);

        self::getEntityManager()->clear();
        $this->assertSame(self::USER, self::getEntityManager()->find(AssetDataTemplate::class, $template->getId())->getOwnerId());
    }

    public function testDelete(): void
    {
        $definition = $this->createDefinition('Title');
        $template = $this->createTemplate('T', self::USER, public: true, attributes: [[$definition, 'v']]);
        $uri = '/asset-data-templates/'.$template->getId();

        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $this->assertStatus(401, 'DELETE', $uri, null);
        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);

        // Attributes are removed along with the template
        self::getEntityManager()->clear();
        $this->assertSame(0, self::getEntityManager()->getRepository(TemplateAttribute::class)->count([]));
        // ...but not their definition
        $this->assertNotNull(self::getEntityManager()->find(AttributeDefinition::class, $definition->getId()));
    }

    public function testGetTemplateAttribute(): void
    {
        $visible = $this->createDefinition('Visible');
        $hidden = $this->createDefinition('Hidden', policyPublic: false);
        $template = $this->createTemplate('T', self::USER, public: false, attributes: [[$visible, 'shown'], [$hidden, 'secret']]);
        [$visibleAttr, $hiddenAttr] = $this->getTemplateAttributes($template);

        $data = $this->apiJson('GET', '/template-attributes/'.$visibleAttr->getId(), self::USER);
        $this->assertSame('shown', $data['value']);

        // Template not readable
        $this->assertStatus(403, 'GET', '/template-attributes/'.$visibleAttr->getId(), self::OTHER);

        // Template readable but policy not
        $this->grantUserOnObject(self::OTHER, $template, PermissionInterface::VIEW);
        $this->assertStatus(200, 'GET', '/template-attributes/'.$visibleAttr->getId(), self::OTHER);
        $this->assertStatus(403, 'GET', '/template-attributes/'.$hiddenAttr->getId(), self::OTHER);

        $this->assertStatus(404, 'GET', '/template-attributes/00000000-0000-4000-8000-000000000000', self::USER);
    }

    public function testTemplateAttributeWriteAccessIsBoundToTemplateEdition(): void
    {
        $definition = $this->createDefinition('Title');
        $template = $this->createTemplate('T', self::USER, public: true, attributes: [[$definition, 'v']]);
        [$attr] = $this->getTemplateAttributes($template);
        $uri = '/template-attributes/'.$attr->getId();

        $this->assertStatus(403, 'PUT', $uri, self::OTHER, ['value' => 'Hijack']);
        $this->assertStatus(403, 'DELETE', $uri, self::OTHER);
        $response = static::createClient()->request('PATCH', $uri, [
            'headers' => array_merge(self::authHeaders(self::OTHER), ['Content-Type' => 'application/merge-patch+json']),
            'json' => ['value' => 'Hijack'],
        ]);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUpdateAndDeleteTemplateAttribute(): void
    {
        $this->markTestIncomplete('BUG: PUT/PATCH/DELETE /template-attributes/{id} answer 500 "Processor TemplateAttributeInputTransformer not found": the resource declares an input transformer (not a state processor) as its processor, and AttributeInput as input (no input transformer supports it for TemplateAttribute), see src/Entity/Template/TemplateAttribute.php:35-37');

        $definition = $this->createDefinition('Title');
        $template = $this->createTemplate('T', self::USER, attributes: [[$definition, 'v']]);
        [$attr] = $this->getTemplateAttributes($template);
        $uri = '/template-attributes/'.$attr->getId();

        $this->assertSame('updated', $this->apiJson('PUT', $uri, self::USER, ['value' => 'updated'])['value']);

        $response = static::createClient()->request('PATCH', $uri, [
            'headers' => array_merge(self::authHeaders(self::USER), ['Content-Type' => 'application/merge-patch+json']),
            'json' => ['value' => 'patched'],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('patched', $response->toArray()['value']);

        $this->assertStatus(204, 'DELETE', $uri, self::USER);
        $this->assertStatus(404, 'GET', $uri, self::USER);
    }

    private function createDefinition(string $name, ?Workspace $workspace = null, bool $policyPublic = true): AttributeDefinition
    {
        $workspace ??= $this->workspace;

        return $this->createAttributeDefinition([
            'name' => $name,
            'workspace' => $workspace,
            'policy' => $this->createAttributePolicy([
                'name' => $name.' policy',
                'workspace' => $workspace,
                'public' => $policyPublic,
            ]),
        ]);
    }

    /**
     * @param list<array{AttributeDefinition, string}> $attributes
     */
    private function createTemplate(
        string $name,
        string $ownerId,
        bool $public = false,
        array $attributes = [],
        ?Collection $collection = null,
        bool $includeChildren = false,
    ): AssetDataTemplate {
        $em = self::getEntityManager();
        $template = new AssetDataTemplate();
        $template->setName($name);
        $template->setOwnerId($ownerId);
        $template->setWorkspace($em->find(Workspace::class, $this->workspace->getId()));
        $template->setPublic($public);
        $template->setCollection($collection ? $em->find(Collection::class, $collection->getId()) : null);
        $template->setIncludeCollectionChildren($includeChildren);
        foreach ($attributes as $i => [$definition, $value]) {
            $attr = new TemplateAttribute();
            $attr->setDefinition($em->find(AttributeDefinition::class, $definition->getId()));
            $attr->setValue($value);
            $attr->setPosition($i);
            $template->addAttribute($attr);
            $em->persist($attr);
        }
        $em->persist($template);
        $em->flush();

        return $template;
    }

    /**
     * @return TemplateAttribute[]
     */
    private function getTemplateAttributes(AssetDataTemplate $template): array
    {
        return self::getEntityManager()->getRepository(TemplateAttribute::class)->findBy(['template' => $template->getId()], ['position' => 'ASC']);
    }
}
