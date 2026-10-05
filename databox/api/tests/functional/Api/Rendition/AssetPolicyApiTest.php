<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\AssetPolicy\AssetPolicy;
use App\Entity\Core\AssetPolicy\AssetPolicyUser;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * /asset-policies (complements AssetPolicyTest): rights, update/delete, and
 * the effect of a "hide_rendition" policy on the rendition endpoints.
 */
final class AssetPolicyApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string EDITOR = KeycloakClientTestMock::USER_UID;
    private const string READER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    private function createAssetPolicy(Workspace $workspace, string $name, array $userIds, array $actions, array $options = []): AssetPolicy
    {
        $em = self::getEntityManager();

        $policy = new AssetPolicy();
        $policy->setWorkspace($workspace);
        $policy->setOwnerId($options['ownerId'] ?? self::EDITOR);
        $policy->setName($name);
        $policy->setActions($actions);
        $policy->setConditions($options['conditions'] ?? []);
        $policy->setEnabled($options['enabled'] ?? true);
        foreach ($userIds as $userId) {
            $u = new AssetPolicyUser();
            $u->setPolicy($policy);
            $u->setUserType(AssetPolicyUser::TYPE_USER);
            $u->setUserId($userId);
            $policy->getUsers()->add($u);
        }
        $em->persist($policy);
        $em->flush();

        return $policy;
    }

    public function testListRights(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [$wsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $policy = $this->createAssetPolicy($ws, 'Hide', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']]);
        $this->createAssetPolicy($wsB, 'Other', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']]);

        $response = $this->jsonRequest('GET', '/asset-policies', self::EDITOR, options: ['query' => ['workspaceId' => $ws->getId()]]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([$policy->getId()], $this->memberIds($response), 'Only the policies of the requested workspace');

        $this->jsonRequest('GET', '/asset-policies', self::EDITOR, options: ['query' => ['workspaceId' => $wsB->getId()]]);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', '/asset-policies', self::EDITOR, options: ['query' => ['workspaceId' => 'f1b4b4a8-0000-4000-8000-000000000000']]);
        $this->assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', '/asset-policies', self::ADMIN, options: ['query' => ['workspaceId' => $wsB->getId()]]);
        $this->assertResponseIsSuccessful();
    }

    public function testWorkspaceReaderCannotListPolicies(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $this->createAssetPolicy($ws, 'Hide', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']]);

        $this->jsonRequest('GET', '/asset-policies', self::READER, options: ['query' => ['workspaceId' => $ws->getId()]]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetItemIsRestrictedToWorkspaceEditors(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $policy = $this->createAssetPolicy($ws, 'Hide', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']], [
            'conditions' => [['field' => 'collection', 'operator' => '=', 'value' => '/foo']],
        ]);
        $iri = '/asset-policies/'.$policy->getId();

        $this->jsonRequest('GET', $iri, null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', $iri, self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', $iri, self::EDITOR);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            '@type' => 'asset-policy',
            'name' => 'Hide',
            'enabled' => true,
            'owner' => ['id' => self::EDITOR],
            'users' => [['id' => self::READER]],
            'groups' => [],
            'conditions' => [['field' => 'collection', 'operator' => '=', 'value' => '/foo']],
            'actions' => [['action' => 'hide_rendition', 'definitionId' => 'x']],
        ]);
    }

    public function testCreateRightsAndValidation(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $payload = [
            'workspace' => '/workspaces/'.$ws->getId(),
            'name' => 'Hide HD',
            'users' => [self::READER],
            'conditions' => [],
            'actions' => [['action' => 'hide_rendition', 'definitionId' => 'x']],
        ];

        $this->jsonRequest('POST', '/asset-policies', self::READER, $payload);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->jsonRequest('POST', '/asset-policies', self::EDITOR, array_diff_key($payload, ['workspace' => true]));
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('workspace', $response->toArray(false)['hydra:description']);

        $response = $this->jsonRequest('POST', '/asset-policies', self::EDITOR, array_merge($payload, ['name' => '']));
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('name', $response->toArray(false)['hydra:description']);

        $tooManyUsers = array_map(fn (int $i): string => 'user-'.$i, range(1, 31));
        $this->jsonRequest('POST', '/asset-policies', self::EDITOR, array_merge($payload, ['users' => $tooManyUsers]));
        $this->assertResponseStatusCodeSame(422);

        $response = $this->jsonRequest('POST', '/asset-policies', self::EDITOR, array_merge($payload, ['users' => [], 'groups' => []]));
        $this->assertResponseStatusCodeSame(422);
        $this->assertStringContainsString('At least one user or one group is required.', $response->toArray(false)['hydra:description']);

        $this->jsonRequest('POST', '/asset-policies', self::EDITOR, $payload);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            'name' => 'Hide HD',
            'enabled' => true,
            'owner' => ['id' => self::EDITOR],
            'users' => [['id' => self::READER]],
            'groups' => [],
        ]);
    }

    public function testAnonymousCreateIsUnauthorized(): void
    {
        $this->markTestIncomplete('BUG: POST /asset-policies has no "security" expression; an anonymous call reaches AssetPolicyInputTransformer::getStrictUser() (src/Api/InputTransformer/AssetPolicyInputTransformer.php:41) and answers 403 instead of 401.');

        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);

        $this->jsonRequest('POST', '/asset-policies', null, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'name' => 'Anonymous',
            'users' => [self::READER],
            'conditions' => [],
            'actions' => [['action' => 'hide_rendition', 'definitionId' => 'x']],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdate(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [$wsB] = $this->createWorkspaceWithDefaults('ws-b', 'someone-else');
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $policy = $this->createAssetPolicy($ws, 'Hide', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']]);
        $iri = '/asset-policies/'.$policy->getId();

        $this->jsonRequest('PUT', $iri, self::READER, ['name' => 'x']);
        $this->assertResponseStatusCodeSame(403);

        // Partial update: users are kept when neither users nor groups are given
        $this->jsonRequest('PUT', $iri, self::EDITOR, ['name' => 'Renamed', 'enabled' => false]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'name' => 'Renamed',
            'enabled' => false,
            'users' => [['id' => self::READER]],
            'actions' => [['action' => 'hide_rendition', 'definitionId' => 'x']],
        ]);

        // Giving users (or groups) replaces the whole audience
        $response = $this->jsonRequest('PUT', $iri, self::EDITOR, ['users' => [self::EDITOR]]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([self::EDITOR], array_column($response->toArray()['users'], 'id'));

        // Cannot move the policy into a workspace the user cannot edit
        $this->jsonRequest('PUT', $iri, self::EDITOR, ['workspace' => '/workspaces/'.$wsB->getId()]);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('PATCH', $iri, self::EDITOR, ['name' => 'Patched']);
        $this->assertResponseStatusCodeSame(405);
    }

    public function testDelete(): void
    {
        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $policy = $this->createAssetPolicy($ws, 'Hide', [self::READER], [['action' => 'hide_rendition', 'definitionId' => 'x']]);
        $iri = '/asset-policies/'.$policy->getId();

        $this->jsonRequest('DELETE', $iri, self::READER);
        $this->assertResponseStatusCodeSame(403);

        $this->jsonRequest('DELETE', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', $iri, self::EDITOR);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testHideRenditionPolicyAppliesToTargetedUsersOnly(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $preview = $defaults->renditionDefinitions['preview'];
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $file = $this->createUrlFile($ws);
        $mainRendition = $this->createAssetRendition($asset, $defaults->renditionDefinitions['main'], $file);
        $previewRendition = $this->createAssetRendition($asset, $preview, $file);
        $this->createAssetPolicy($ws, 'Hide preview', [self::READER], [['action' => 'hide_rendition', 'definitionId' => $preview->getId()]]);

        $renditionIds = fn (string $userId): array => $this->memberIds($this->jsonRequest('GET', '/renditions', $userId, options: [
            'query' => ['assetId' => $asset->getId()],
        ]));
        $definitionIds = fn (string $userId): array => $this->memberIds($this->jsonRequest('GET', '/rendition-definitions', $userId, options: [
            'query' => ['assetId' => $asset->getId()],
        ]));

        $this->assertSame([$mainRendition->getId()], $renditionIds(self::READER));
        $this->assertNotContains($preview->getId(), $definitionIds(self::READER));

        $this->assertEqualsCanonicalizing([$mainRendition->getId(), $previewRendition->getId()], $renditionIds(self::EDITOR));
        $this->assertContains($preview->getId(), $definitionIds(self::EDITOR));
    }

    public function testDisabledOrNonMatchingPolicyHasNoEffect(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $preview = $defaults->renditionDefinitions['preview'];
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $previewRendition = $this->createAssetRendition($asset, $preview, $this->createUrlFile($ws));
        $hide = [['action' => 'hide_rendition', 'definitionId' => $preview->getId()]];
        $this->createAssetPolicy($ws, 'Disabled', [self::READER], $hide, ['enabled' => false]);
        $this->createAssetPolicy($ws, 'Other collection', [self::READER], $hide, [
            'conditions' => [['field' => 'collection', 'operator' => '=', 'value' => 'some-other-collection-id']],
        ]);

        $ids = $this->memberIds($this->jsonRequest('GET', '/renditions', self::READER, options: ['query' => ['assetId' => $asset->getId()]]));
        $this->assertSame([$previewRendition->getId()], $ids);
    }

    public function testHideRenditionPolicyIsScopedToItsWorkspace(): void
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        [$wsB] = $this->createWorkspaceWithDefaults('ws-b', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $preview = $defaults->renditionDefinitions['preview'];
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $previewRendition = $this->createAssetRendition($asset, $preview, $this->createUrlFile($ws));
        // Defined on another workspace: ignored for assets of ws-a
        $this->createAssetPolicy($wsB, 'Foreign', [self::READER], [['action' => 'hide_rendition', 'definitionId' => $preview->getId()]]);

        $ids = $this->memberIds($this->jsonRequest('GET', '/renditions', self::READER, options: ['query' => ['assetId' => $asset->getId()]]));
        $this->assertSame([$previewRendition->getId()], $ids);
    }

    public function testMalformedActionIsRejected(): void
    {
        $this->markTestIncomplete('BUG: AssetPolicyInput does not validate the shape of "actions" (src/Api/Model/Input/AssetPolicyInput.php:39): a "hide_rendition" action without "definitionId" is stored, then AssetPolicyManager::applyPolicyToOutput() reads $action[\'definitionId\'] (src/Service/Asset/AssetPolicy/AssetPolicyManager.php:79) on every rendition read of the targeted users, which then fail with a TypeError (500).');

        [$ws] = $this->createWorkspaceWithDefaults('ws-a', self::EDITOR);
        $this->addUserOnWorkspace(self::READER, $ws->getId());
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::EDITOR]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $this->createAssetPolicy($ws, 'Stored malformed', [self::READER], [['action' => 'hide_rendition', 'id' => 'x']]);

        // An already stored malformed action must not break the rendition endpoints
        $this->jsonRequest('GET', '/renditions', self::READER, options: ['query' => ['assetId' => $asset->getId()]]);
        $this->assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/asset-policies', self::EDITOR, [
            'workspace' => '/workspaces/'.$ws->getId(),
            'name' => 'Malformed',
            'users' => [self::READER],
            'conditions' => [],
            'actions' => [['action' => 'hide_rendition', 'id' => 'x']],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }
}
