<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\RenditionDefinition;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /rendition-definitions: listing ACL, workspace isolation, filters, admin-only
 * fields, write rights, upsert by key, sort.
 */
final class RenditionDefinitionApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string READER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    private const string BUILD_DEFINITION = <<<'YAML'
image:
    transformations:
        -
            module: imagine
            options:
                filters:
                    thumbnail:
                        size: [100, 100]
YAML;

    public function testListIsIsolatedByWorkspace(): void
    {
        [$wsA, $defaultsA] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [$wsB, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $this->addUserOnWorkspace(self::READER, $wsA->getId());
        $idsA = array_map(fn (RenditionDefinition $d): string => $d->getId(), array_values($defaultsA->renditionDefinitions));
        $idsB = array_map(fn (RenditionDefinition $d): string => $d->getId(), array_values($defaultsB->renditionDefinitions));

        foreach ([self::EDITOR, self::READER] as $userId) {
            $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', $userId));
            $this->assertResponseIsSuccessful();
            $this->assertEqualsCanonicalizing($idsA, $ids, 'A workspace member only sees the definitions of its workspaces');
        }

        // Explicitly asking for a foreign workspace returns nothing
        $this->assertSame([], $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: [
            'query' => ['workspaceId' => $wsB->getId()],
        ])));

        // Admin sees everything, and can narrow down by workspace
        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::ADMIN));
        $this->assertEqualsCanonicalizing(array_merge($idsA, $idsB), $ids);
        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::ADMIN, options: [
            'query' => ['workspaceIds' => [$wsB->getId()]],
        ]));
        $this->assertEqualsCanonicalizing($idsB, $ids);
    }

    public function testAnonymousListOnlyExposesPublicWorkspaceAndPublicPolicy(): void
    {
        [$publicWs, $publicDefaults] = $this->createWorkspaceWithDefaults('ws-public', self::EDITOR, true);
        $this->createWorkspaceWithDefaults('ws-private', self::EDITOR);
        $restricted = $this->createRenditionPolicy($publicWs, 'Restricted', false, false);
        $hidden = $this->createRenditionDefinition($publicWs, $restricted, 'hidden');

        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', null));
        $this->assertResponseIsSuccessful();

        $expected = array_map(fn (RenditionDefinition $d): string => $d->getId(), array_values($publicDefaults->renditionDefinitions));
        $this->assertEqualsCanonicalizing($expected, $ids);
        $this->assertNotContains($hidden->getId(), $ids);
    }

    public function testWorkspaceOwnerDoesNotListDefinitionsOfANonPublicPolicyWithoutAcl(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $restricted = $this->createRenditionPolicy($ws, 'Restricted', false, false);
        $hidden = $this->createRenditionDefinition($ws, $restricted, 'hidden');

        $this->assertNotContains($hidden->getId(), $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::EDITOR)));
        $this->assertContains($hidden->getId(), $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::ADMIN)));
    }

    public function testListFilters(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $assetOnly = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'Asset only', ['target' => AssetTypeEnum::Asset]);
        $storyOnly = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'Story only', ['target' => AssetTypeEnum::Story]);

        $byName = $this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: ['query' => ['name' => 'only']]);
        $this->assertEqualsCanonicalizing([$assetOnly->getId(), $storyOnly->getId()], $this->memberIds($byName));

        $byTarget = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: [
            'query' => ['target' => AssetTypeEnum::Story->value],
        ]));
        $this->assertContains($storyOnly->getId(), $byTarget);
        $this->assertContains($defaults->renditionDefinitions['main']->getId(), $byTarget, 'Target "Both" matches stories');
        $this->assertNotContains($assetOnly->getId(), $byTarget);

        $byWorkspace = $this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: ['query' => ['workspaceId' => $ws->getId()]]);
        $this->assertSame(5, $byWorkspace->toArray()['hydra:totalItems']);
    }

    public function testListIsOrderedByPriority(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $high = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'High', ['priority' => 10]);
        $low = $this->createRenditionDefinition($ws, $defaults->renditionPolicy, 'Low', ['priority' => -10]);

        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::EDITOR));
        $this->assertSame($high->getId(), $ids[0]);
        $this->assertSame($low->getId(), $ids[count($ids) - 1]);
    }

    public function testAdminFieldsAreOnlyExposedToWorkspaceEditors(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $main = $defaults->renditionDefinitions['main'];
        $preview = $defaults->renditionDefinitions['preview'];

        $data = $this->jsonRequest('GET', '/rendition-definitions/'.$preview->getId(), self::EDITOR)->toArray();
        $this->assertSame('Preview', $data['name']);
        $this->assertTrue($data['useAsPreview']);
        $this->assertFalse($data['useAsMain']);
        $this->assertSame(RenditionDefinition::BUILD_MODE_CUSTOM, $data['buildMode']);
        $this->assertNotEmpty($data['definition']);
        $this->assertSame('/rendition-definitions/'.$main->getId(), $data['parent']['@id'] ?? $data['parent']);
        $this->assertSame(AssetTypeEnum::Both->value, $data['target']);

        $data = $this->jsonRequest('GET', '/rendition-definitions/'.$preview->getId(), self::READER)->toArray();
        $this->assertSame('Preview', $data['name']);
        foreach (['buildMode', 'useAsMain', 'useAsPreview', 'useAsThumbnail', 'useAsAnimatedThumbnail', 'definition', 'priority'] as $adminField) {
            $this->assertArrayNotHasKey($adminField, $data, $adminField.' must be hidden to a mere reader');
        }
        $this->assertArrayHasKey('substitutable', $data);
        $this->assertArrayHasKey('download', $data);
    }

    public function testDisplayNameIsTranslated(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];

        $this->jsonRequest('PUT', '/rendition-definitions/'.$preview->getId(), self::EDITOR, [
            'translations' => ['name' => ['fr' => 'Aperçu', 'en' => '']],
        ]);
        $this->assertResponseIsSuccessful();

        $data = $this->jsonRequest('GET', '/rendition-definitions/'.$preview->getId(), self::EDITOR, options: [
            'headers' => ['Accept-Language' => 'fr'],
        ])->toArray();
        $this->assertSame('Preview', $data['name']);
        $this->assertSame('Aperçu', $data['displayName']);
        // Empty translations are dropped
        $this->assertSame(['name' => ['fr' => 'Aperçu']], $data['translations']);

        $data = $this->jsonRequest('GET', '/rendition-definitions/'.$preview->getId(), self::EDITOR, options: [
            'headers' => ['Accept-Language' => 'de'],
        ])->toArray();
        $this->assertSame('Preview', $data['displayName']);
    }

    public function testGetDefinitionOfAPrivateWorkspaceIsNotReadableByOutsiders(): void
    {
        $this->markTestIncomplete('BUG: RenditionDefinitionVoter grants READ to anyone (even anonymous) on GET /rendition-definitions/{id}, while the collection applies the workspace/policy ACL.');

        [$ws] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $restricted = $this->createRenditionPolicy($ws, 'Restricted', false, false);
        $secret = $this->createRenditionDefinition($ws, $restricted, 'secret');

        $this->jsonRequest('GET', '/rendition-definitions/'.$secret->getId(), null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', '/rendition-definitions/'.$secret->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateRights(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $payload = [
            'workspace' => '/workspaces/'.$ws->getId(),
            'policy' => '/rendition-policies/'.$defaults->renditionPolicy->getId(),
            'name' => 'Square',
        ];

        $this->jsonRequest('POST', '/rendition-definitions', null, $payload);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('POST', '/rendition-definitions', self::READER, $payload);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, array_merge($payload, [
            'parent' => '/rendition-definitions/'.$defaults->renditionDefinitions['main']->getId(),
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => self::BUILD_DEFINITION,
            'useAsThumbnail' => true,
            'priority' => 7,
            'substitutable' => false,
            'download' => false,
            'target' => AssetTypeEnum::Asset->value,
            'labels' => ['foo' => 'bar'],
        ]));
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'rendition-definition',
            'name' => 'Square',
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'useAsThumbnail' => true,
            'useAsMain' => false,
            'priority' => 7,
            'substitutable' => false,
            'download' => false,
            'target' => AssetTypeEnum::Asset->value,
            'labels' => ['foo' => 'bar'],
        ]);
        $data = $response->toArray();
        $this->assertSame('/rendition-definitions/'.$defaults->renditionDefinitions['main']->getId(), $data['parent']['@id'] ?? $data['parent']);

        $this->jsonRequest('POST', '/rendition-definitions', self::ADMIN, array_merge($payload, ['name' => 'By admin']));
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateValidation(): void
    {
        [$wsA, $defaultsA] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', self::EDITOR);
        $workspaceIri = '/workspaces/'.$wsA->getId();
        $policyIri = '/rendition-policies/'.$defaultsA->renditionPolicy->getId();

        $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, ['name' => 'No workspace', 'policy' => $policyIri]);
        $this->assertResponseStatusCodeSame(400);

        $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, ['workspace' => $workspaceIri, 'name' => 'No policy']);
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, ['workspace' => $workspaceIri, 'policy' => $policyIri]);
        $this->assertResponseStatusCodeSame(422);

        // Policy and parent must belong to the same workspace
        $response = $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, [
            'workspace' => $workspaceIri,
            'name' => 'Foreign policy',
            'policy' => '/rendition-policies/'.$defaultsB->renditionPolicy->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('same workspace', $response->toArray(false)['hydra:description']);

        $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, [
            'workspace' => $workspaceIri,
            'name' => 'Foreign parent',
            'policy' => $policyIri,
            'parent' => '/rendition-definitions/'.$defaultsB->renditionDefinitions['main']->getId(),
        ]);
        $this->assertResponseStatusCodeSame(422);

        // The build definition is validated
        $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, [
            'workspace' => $workspaceIri,
            'name' => 'Invalid build',
            'policy' => $policyIri,
            'buildMode' => RenditionDefinition::BUILD_MODE_CUSTOM,
            'definition' => "image:\n    transformations:\n        -\n            module: unknown_module\n",
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateWithAnExistingKeyUpdatesTheExistingDefinition(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];

        $response = $this->jsonRequest('POST', '/rendition-definitions', self::EDITOR, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'key' => 'preview',
            'name' => 'Preview v2',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($preview->getId(), $response->toArray()['id']);
        $this->assertSame('Preview v2', $response->toArray()['name']);

        $list = $this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: ['query' => ['workspaceId' => $ws->getId()]]);
        $this->assertSame(3, $list->toArray()['hydra:totalItems']);
    }

    public function testPatchOnlyChangesProvidedFields(): void
    {
        [, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];

        $response = $this->jsonRequest('PATCH', '/rendition-definitions/'.$preview->getId(), self::EDITOR, [
            'name' => 'Renamed',
            'useAsAnimatedThumbnail' => true,
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Renamed', $data['name']);
        $this->assertTrue($data['useAsAnimatedThumbnail']);
        $this->assertTrue($data['useAsPreview']);
        $this->assertSame(RenditionDefinition::BUILD_MODE_CUSTOM, $data['buildMode']);
        $this->assertSame(0, $data['priority']);
    }

    public function testPatchKeepsTheParent(): void
    {
        $this->markTestIncomplete('BUG: RenditionDefinitionInputTransformer always calls setParent($data->parent) (src/Api/InputTransformer/RenditionDefinitionInputTransformer.php:59), so any PUT/PATCH that omits "parent" detaches the definition from its parent.');

        [, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];
        $mainIri = '/rendition-definitions/'.$defaults->renditionDefinitions['main']->getId();

        $response = $this->jsonRequest('PATCH', '/rendition-definitions/'.$preview->getId(), self::EDITOR, [
            'name' => 'Renamed',
        ]);
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame($mainIri, $data['parent']['@id'] ?? $data['parent'] ?? null);
    }

    public function testPatchKeepsTheBuildDefinition(): void
    {
        $this->markTestIncomplete('BUG: RenditionDefinitionInput::$definition defaults to \'\' (src/Api/Model/Input/RenditionDefinitionInput.php:111), so any PUT/PATCH that omits "definition" wipes the build definition.');

        [, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $preview = $defaults->renditionDefinitions['preview'];
        $definition = $preview->getDefinition();
        $this->assertNotEmpty($definition);

        $response = $this->jsonRequest('PATCH', '/rendition-definitions/'.$preview->getId(), self::EDITOR, [
            'name' => 'Renamed',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($definition, $response->toArray()['definition']);
    }

    public function testUpdateAndDeleteRights(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $thumbnail = $defaults->renditionDefinitions['thumbnail'];
        $iri = '/rendition-definitions/'.$thumbnail->getId();

        $this->jsonRequest('PUT', $iri, null, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(401);
        $this->jsonRequest('PUT', $iri, self::READER, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(403);
        $this->jsonRequest('PATCH', $iri, self::READER, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(403);
        $this->jsonRequest('DELETE', $iri, self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('PUT', $iri, self::EDITOR, ['name' => 'Thumb']);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['name' => 'Thumb']);

        $this->jsonRequest('DELETE', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteRemovesTheAssetRenditions(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $thumbnail = $defaults->renditionDefinitions['thumbnail'];
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $rendition = $this->createAssetRendition($asset, $thumbnail, $this->createUrlFile($ws));
        $renditionId = $rendition->getId();

        $this->jsonRequest('DELETE', '/rendition-definitions/'.$thumbnail->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/renditions/'.$renditionId, self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSortSetsPrioritiesInGivenOrder(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $ids = [
            $defaults->renditionDefinitions['thumbnail']->getId(),
            $defaults->renditionDefinitions['main']->getId(),
            $defaults->renditionDefinitions['preview']->getId(),
        ];

        $response = $this->jsonRequest('POST', '/rendition-definitions/sort', self::EDITOR, $ids);
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $response->getContent());

        $listed = $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::EDITOR, options: ['query' => ['workspaceId' => $ws->getId()]]));
        $this->assertSame($ids, $listed);

        $em = self::getEntityManager();
        $this->assertSame(2, $em->find(RenditionDefinition::class, $ids[0])->getPriority());
        $this->assertSame(0, $em->find(RenditionDefinition::class, $ids[2])->getPriority());
    }

    public function testSortOnlyAffectsTheWorkspaceOfTheLastItem(): void
    {
        [, $defaultsA] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $foreign = $defaultsB->renditionDefinitions['main'];
        $foreign->setPriority(42);
        self::getEntityManager()->flush();

        // The list is reversed: the workspace is taken from the last item (the lowest priority one)
        $this->jsonRequest('POST', '/rendition-definitions/sort', self::EDITOR, [
            $foreign->getId(),
            $defaultsA->renditionDefinitions['main']->getId(),
        ]);
        $this->assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $this->assertSame(42, $em->find(RenditionDefinition::class, $foreign->getId())->getPriority(), 'Items of another workspace are left untouched');
        $this->assertSame(0, $em->find(RenditionDefinition::class, $defaultsA->renditionDefinitions['main']->getId())->getPriority());
    }

    public function testSortRights(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $ids = [$defaults->renditionDefinitions['main']->getId()];

        $this->jsonRequest('POST', '/rendition-definitions/sort', null, $ids);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('POST', '/rendition-definitions/sort', self::READER, $ids);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('POST', '/rendition-definitions/sort', self::ADMIN, $ids);
        $this->assertResponseIsSuccessful();

        // Empty list is a no-op
        $this->jsonRequest('POST', '/rendition-definitions/sort', self::EDITOR, []);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/rendition-definitions/sort', self::EDITOR, ['ea5d2f1c-3c0b-4b8e-9f2a-000000000000']);
        $this->assertResponseStatusCodeSame(404);
    }
}
