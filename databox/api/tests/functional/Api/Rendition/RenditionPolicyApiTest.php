<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /rendition-policies: workspace isolation, rights, uniqueness, cascade, and
 * the effect of a non-public policy on the renditions it covers.
 */
final class RenditionPolicyApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string READER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    public function testListRequiresAuthenticationAndIsIsolatedByWorkspace(): void
    {
        [$wsA, $defaultsA] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR, true);
        [$wsB, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $restrictedA = $this->createRenditionPolicy($wsA, 'Restricted', false, false);

        $this->jsonRequest('GET', '/rendition-policies', null);
        $this->assertResponseStatusCodeSame(401);

        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-policies', self::EDITOR));
        $this->assertEqualsCanonicalizing([$defaultsA->renditionPolicy->getId(), $restrictedA->getId()], $ids);

        // A reader of a public workspace sees its policies (including non-public ones)
        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-policies', self::READER));
        $this->assertEqualsCanonicalizing([$defaultsA->renditionPolicy->getId(), $restrictedA->getId()], $ids);

        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-policies', self::EDITOR, options: [
            'query' => ['workspaceId' => $wsB->getId()],
        ]));
        $this->assertSame([], $ids);

        $ids = $this->memberIds($this->jsonRequest('GET', '/rendition-policies', self::ADMIN, options: [
            'query' => ['workspaceId' => $wsB->getId()],
        ]));
        $this->assertSame([$defaultsB->renditionPolicy->getId()], $ids);
    }

    public function testGetItem(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [, $defaultsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $iri = '/rendition-policies/'.$defaults->renditionPolicy->getId();

        $this->jsonRequest('GET', $iri, null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', $iri, self::READER);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            '@type' => 'rendition-policy',
            'name' => 'Public',
            'public' => true,
            'editable' => true,
        ]);

        $this->jsonRequest('GET', '/rendition-policies/'.$defaultsB->renditionPolicy->getId(), self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', '/rendition-policies/'.$defaultsB->renditionPolicy->getId(), self::ADMIN);
        $this->assertResponseIsSuccessful();
    }

    public function testCreateRightsAndUniqueness(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [$wsB] = $this->createWorkspaceWithDefaults('ws-b', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $payload = [
            'workspace' => '/workspaces/'.$ws->getId(),
            'name' => 'Restricted',
            'public' => false,
            'editable' => false,
            'labels' => ['a' => 'b'],
        ];

        $this->jsonRequest('POST', '/rendition-policies', self::READER, $payload);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('POST', '/rendition-policies', self::EDITOR, $payload);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'Restricted',
            'public' => false,
            'editable' => false,
            'labels' => ['a' => 'b'],
        ]);

        // Name is unique per workspace...
        $response = $this->jsonRequest('POST', '/rendition-policies', self::EDITOR, $payload);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame('name', $response->toArray(false)['violations'][0]['propertyPath']);

        // ... but not across workspaces
        $this->jsonRequest('POST', '/rendition-policies', self::EDITOR, array_merge($payload, [
            'workspace' => '/workspaces/'.$wsB->getId(),
        ]));
        $this->assertResponseStatusCodeSame(201);

        // "editable" is mandatory
        $this->jsonRequest('POST', '/rendition-policies', self::EDITOR, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'name' => 'No editable flag',
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateAndDeleteRights(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $policy = $this->createRenditionPolicy($ws, 'Restricted', false, false);
        $iri = '/rendition-policies/'.$policy->getId();

        $this->jsonRequest('PATCH', $iri, self::READER, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(403);
        $this->jsonRequest('PATCH', $iri, self::READER, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(403);
        $this->jsonRequest('DELETE', $iri, self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('PATCH', $iri, self::EDITOR, ['public' => true]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['name' => 'Restricted', 'public' => true, 'editable' => false]);

        $this->jsonRequest('PATCH', $iri, self::EDITOR, ['name' => 'Renamed']);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['name' => 'Renamed', 'public' => true]);

        $this->jsonRequest('DELETE', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteCascadesToDefinitionsAndRenditions(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $policy = $this->createRenditionPolicy($ws, 'Temporary');
        $definition = $this->createRenditionDefinition($ws, $policy, 'temp');
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $rendition = $this->createAssetRendition($asset, $definition, $this->createUrlFile($ws));

        $this->jsonRequest('DELETE', '/rendition-policies/'.$policy->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/rendition-definitions/'.$definition->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
        $this->jsonRequest('GET', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRenditionOfANonPublicPolicyIsOnlyVisibleWithChildViewAcl(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $restricted = $this->createRenditionPolicy($ws, 'Restricted', false, false);
        $hdDefinition = $this->createRenditionDefinition($ws, $restricted, 'hd');
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR, 'public' => true]);
        $file = $this->createUrlFile($ws);
        $public = $this->createAssetRendition($asset, $defaults->renditionDefinitions['main'], $file);
        $hd = $this->createAssetRendition($asset, $hdDefinition, $file);

        $listedIds = fn (string $userId): array => array_column(
            $this->jsonRequest('GET', '/renditions', $userId, options: ['query' => ['assetId' => $asset->getId()]])->toArray()['member'],
            'id'
        );

        $this->assertSame([$public->getId()], $listedIds(self::READER));
        $this->jsonRequest('GET', '/renditions/'.$hd->getId(), self::READER);
        $this->assertResponseStatusCodeSame(403);

        // Even the asset owner (workspace owner) needs an ACL on a non-public policy
        $this->assertSame([$public->getId()], $listedIds(self::EDITOR));

        // VIEW on the policy reveals its definitions, but not the renditions
        $this->grantUserOnObject(self::READER, $restricted, PermissionInterface::VIEW);
        $this->assertSame([$public->getId()], $listedIds(self::READER));
        $this->assertContains($hdDefinition->getId(), $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', self::READER)));

        // CHILD_VIEW grants the renditions
        $this->grantUserOnObject(self::READER, $restricted, PermissionInterface::VIEW | PermissionInterface::CHILD_VIEW);
        $this->assertEqualsCanonicalizing([$public->getId(), $hd->getId()], $listedIds(self::READER));
        $this->jsonRequest('GET', '/renditions/'.$hd->getId(), self::READER);
        $this->assertResponseIsSuccessful();

        // Admin sees everything
        $this->assertEqualsCanonicalizing([$public->getId(), $hd->getId()], $listedIds(self::ADMIN));
    }

    public function testNonEditablePolicyForbidsRenditionWritesWithoutChildEditAcl(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $locked = $this->createRenditionPolicy($ws, 'Locked', true, false);
        $definition = $this->createRenditionDefinition($ws, $locked, 'locked-def');
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $rendition = $this->createAssetRendition($asset, $definition, $this->createUrlFile($ws));

        $this->jsonRequest('POST', '/renditions', self::EDITOR, [
            'assetId' => $asset->getId(),
            'definitionId' => $definition->getId(),
            'sourceFile' => [
                'url' => 'https://cdn.example.com/other.jpg',
                'originalName' => 'other.jpg',
                'type' => 'image/jpeg',
                'isPrivate' => false,
                'importFile' => false,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('DELETE', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(403);

        $this->grantUserOnObject(self::EDITOR, $locked, PermissionInterface::CHILD_EDIT);
        $this->jsonRequest('DELETE', '/renditions/'.$rendition->getId(), self::EDITOR);
        $this->assertResponseStatusCodeSame(204);
    }
}
