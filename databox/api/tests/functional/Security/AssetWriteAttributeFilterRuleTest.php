<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use App\Tests\Functional\Api\Asset\AssetApiTestTrait;

/**
 * The attribute filter rules apply to every action on an asset, not only to its read:
 * an asset hidden by a rule is invisible and untouchable for the targeted users.
 */
class AssetWriteAttributeFilterRuleTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    private const string HIDING_CONDITION = 'category = "press"';

    public function testActionsOnHiddenAssetAreDenied(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);
        foreach ($assets as $asset) {
            $this->grantUserOnObject(self::OWNER, $asset, PermissionInterface::VIEW
                | PermissionInterface::EDIT
                | PermissionInterface::OPERATOR
                | PermissionInterface::DELETE
                | PermissionInterface::SHARE);
        }

        self::getAttributeFilterManager()->saveRule($workspace, [self::OWNER], [], self::HIDING_CONDITION);

        $hiddenId = $assets['Internal']->getId();
        $visibleId = $assets['Press']->getId();

        $this->request('PATCH', '/assets/'.$hiddenId, self::OWNER, []);
        $this->assertResponseStatusCodeSame(403);
        $this->request('PATCH', '/assets/'.$visibleId, self::OWNER, []);
        $this->assertResponseStatusCodeSame(200);

        $this->request('PUT', '/assets/'.$hiddenId.'/trigger-workflow', self::OWNER, []);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets/'.$hiddenId.'/attributes', self::OWNER, ['actions' => []]);
        $this->assertResponseStatusCodeSame(403);
        $this->request('POST', '/assets/'.$visibleId.'/attributes', self::OWNER, ['actions' => []]);
        $this->assertResponseStatusCodeSame(201);

        $this->request('POST', '/assets/delete-multiple', self::OWNER, ['ids' => [$hiddenId]]);
        $this->assertResponseStatusCodeSame(403);
        $this->request('DELETE', '/assets/'.$hiddenId, self::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->reloadAsset($hiddenId)->isDeleted());
    }

    public function testOwnerOfHiddenAssetCannotActOnIt(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace, ownerId: self::OWNER);

        self::getAttributeFilterManager()->saveRule($workspace, [self::OWNER], [], self::HIDING_CONDITION);

        $this->request('PATCH', '/assets/'.$assets['Internal']->getId(), self::OWNER, []);
        $this->assertResponseStatusCodeSame(403);
        $this->request('DELETE', '/assets/'.$assets['Internal']->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('PATCH', '/assets/'.$assets['Press']->getId(), self::OWNER, []);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAttributesOfHiddenAssetAreDenied(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace, ownerId: self::OWNER);

        self::getAttributeFilterManager()->saveRule($workspace, [self::OWNER], [], self::HIDING_CONDITION);

        $hiddenAttributeId = $this->findCategoryAttributeId($assets['Internal']);
        $visibleAttributeId = $this->findCategoryAttributeId($assets['Press']);

        $this->request('GET', '/attributes/'.$hiddenAttributeId, self::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('PATCH', '/attributes/'.$hiddenAttributeId, self::OWNER, ['value' => 'press']);
        $this->assertResponseStatusCodeSame(403);
        $this->request('DELETE', '/attributes/'.$hiddenAttributeId, self::OWNER);
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/attributes/'.$visibleAttributeId, self::OWNER);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAdminActsOnHiddenAsset(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        // Without target: the rule applies to the admin too
        self::getAttributeFilterManager()->saveRule($workspace, [], [], self::HIDING_CONDITION);

        $hiddenId = $assets['Internal']->getId();
        $this->request('GET', '/assets/'.$hiddenId, self::ADMIN);
        $this->assertResponseStatusCodeSame(200);
        $this->request('PATCH', '/assets/'.$hiddenId, self::ADMIN, []);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testAssetScopeBypassesTheRules(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        // Without target: the rule applies to the clients too
        self::getAttributeFilterManager()->saveRule($workspace, [], [], self::HIDING_CONDITION);

        $hiddenId = $assets['Internal']->getId();
        $this->request('GET', '/assets/'.$hiddenId, null, options: [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset:read')],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->request('PATCH', '/assets/'.$hiddenId, null, [], options: [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getClientCredentialJwt('asset:edit')],
        ]);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testCreateAssetInWorkspaceWithRules(): void
    {
        $workspace = $this->createWorkspace(['ownerId' => self::OWNER]);

        self::getAttributeFilterManager()->saveRule($workspace, [self::OWNER], [], self::HIDING_CONDITION);

        $data = $this->request('POST', '/assets', self::OWNER, [
            'workspace' => '/workspaces/'.$workspace->getId(),
            'name' => 'Without category',
        ])->toArray();
        $this->assertResponseStatusCodeSame(201);

        // Once created, the asset is hidden by the rule
        $this->request('GET', '/assets/'.$data['id'], self::OWNER);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @return array<string, Asset>
     */
    private function createCategorizedAssets(Workspace $workspace, string $ownerId = 'custom_owner'): array
    {
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);

        $assets = [];
        foreach ([
            'Press' => 'press',
            'Internal' => 'internal',
        ] as $assetName => $category) {
            $assets[$assetName] = $this->createAsset([
                'workspace' => $workspace,
                'name' => $assetName,
                'ownerId' => $ownerId,
                'public' => 'custom_owner' === $ownerId,
                'attributes' => [[
                    'definition' => $definition,
                    'value' => $category,
                ]],
            ]);
        }

        return $assets;
    }

    private function findCategoryAttributeId(Asset $asset): string
    {
        $attribute = self::getEntityManager()->getRepository(Attribute::class)->findOneBy([
            'asset' => $asset->getId(),
        ]);
        $this->assertInstanceOf(Attribute::class, $attribute);

        return $attribute->getId();
    }
}
