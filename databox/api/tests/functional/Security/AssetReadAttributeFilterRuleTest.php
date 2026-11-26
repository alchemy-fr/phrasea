<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Share;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The attribute filter rules apply to an asset read by ID, as they do in the search.
 * None of these assets is indexed: the rules are evaluated on the asset itself.
 */
class AssetReadAttributeFilterRuleTest extends AbstractDataboxTestCase
{
    /**
     * @param array<string, int> $expectedCodes response code by asset name
     */
    #[DataProvider('getAttributeConditionsDataSet')]
    public function testReadAssetWithAttributeFilterRule(string $condition, array $expectedCodes): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        self::getAttributeFilterManager()->saveRule($workspace, [KeycloakClientTestMock::USER_UID], [], $condition);

        $this->assertReadCodes($assets, $expectedCodes);
    }

    public static function getAttributeConditionsDataSet(): array
    {
        return [
            'equals' => ['category = "press"', ['Press' => 200, 'Internal' => 403, 'None' => 403]],
            'not equals' => ['NOT (category = "press")', ['Press' => 403, 'Internal' => 200, 'None' => 200]],
            'in' => ['category IN ("press", "internal")', ['Press' => 200, 'Internal' => 200, 'None' => 403]],
            'missing' => ['category IS MISSING', ['Press' => 403, 'Internal' => 403, 'None' => 200]],
            'contains' => ['category CONTAINS "TERN"', ['Press' => 403, 'Internal' => 200, 'None' => 403]],
            'built-in' => ['@privacy = 5', ['Press' => 200, 'Internal' => 200, 'None' => 200]],
            // Invalid condition (unknown field): fail-closed
            'unknown field' => ['unknown_field = "press"', ['Press' => 403, 'Internal' => 403, 'None' => 403]],
            'invalid syntax' => ['category = = "press"', ['Press' => 403, 'Internal' => 403, 'None' => 403]],
            // Built-in fields a rule cannot use (stored before they were refused): fail-closed
            'similar' => ['@similar = "f2b2a4b0-1b8e-4d6c-9d43-7b8a3f1f5e11"', ['Press' => 403, 'Internal' => 403, 'None' => 403]],
            'score' => ['@score > 1', ['Press' => 403, 'Internal' => 403, 'None' => 403]],
            'position' => ['@position = 1', ['Press' => 403, 'Internal' => 403, 'None' => 403]],
        ];
    }

    #[DataProvider('getTargetingDataSet')]
    public function testAttributeFilterRuleTargeting(array $userIds, bool $applies): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        self::getAttributeFilterManager()->saveRule($workspace, $userIds, [], 'category = "press"');

        $this->assertReadCodes($assets, [
            'Press' => 200,
            'Internal' => $applies ? 403 : 200,
        ]);
    }

    public static function getTargetingDataSet(): array
    {
        return [
            'no target applies to everyone' => [[], true],
            'targeted user' => [[KeycloakClientTestMock::USER_UID], true],
            'other user only' => [[KeycloakClientTestMock::OTHER_USER_UID], false],
            'multiple users including current' => [[KeycloakClientTestMock::OTHER_USER_UID, KeycloakClientTestMock::USER_UID], true],
        ];
    }

    public function testRulesOfTheGroupsOfTheUserApply(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        self::getAttributeFilterManager()->saveRule($workspace, [], [KeycloakClientTestMock::GROUP_UID], 'category = "press"');

        $this->assertReadCodes($assets, ['Press' => 200, 'Internal' => 200]);
        $this->assertReadCodes($assets, ['Press' => 200, 'Internal' => 403], [KeycloakClientTestMock::GROUP_UID]);
    }

    public function testUntargetedRulesApplyToAnonymousUsers(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);

        self::getAttributeFilterManager()->saveRule($workspace, [], [], 'category = "press"');

        $client = static::createClient();
        $client->request('GET', '/assets/'.$assets['Press']->getId());
        $this->assertResponseStatusCodeSame(200);
        $response = $client->request('GET', '/assets/'.$assets['Internal']->getId());
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function testWorkspaceOwnerIsFiltered(): void
    {
        $workspace = $this->createWorkspace([
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $assets = $this->createCategorizedAssets($workspace, public: false);

        self::getAttributeFilterManager()->saveRule($workspace, [KeycloakClientTestMock::USER_UID], [], 'category = "press"');

        $this->assertReadCodes($assets, ['Press' => 200, 'Internal' => 403]);
    }

    public function testRuleOfAnotherWorkspaceHasNoEffect(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);
        $otherWorkspace = $this->createWorkspace([
            'public' => true,
            'slug' => 'other-workspace',
        ]);

        self::getAttributeFilterManager()->saveRule($otherWorkspace, [KeycloakClientTestMock::USER_UID], [], 'unknown_field = "press"');

        $this->assertReadCodes($assets, ['Press' => 200, 'Internal' => 200, 'None' => 200]);
    }

    public function testShareOwnerLosesTheShareOfAnAssetHiddenByARule(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assets = $this->createCategorizedAssets($workspace);
        $em = self::getEntityManager();
        $shares = [];
        foreach (['Press', 'Internal'] as $name) {
            $share = new Share();
            $share->setOwnerId(KeycloakClientTestMock::USER_UID);
            $share->addAsset($assets[$name]);
            $em->persist($share);
            $shares[$name] = $share;
        }
        $em->flush();

        self::getAttributeFilterManager()->saveRule($workspace, [KeycloakClientTestMock::USER_UID], [], 'category = "press"');

        $client = static::createClient();
        $headers = [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
        ];
        $client->request('GET', '/shares/'.$shares['Press']->getId(), ['headers' => $headers]);
        $this->assertResponseStatusCodeSame(200);
        $client->request('GET', '/shares/'.$shares['Internal']->getId(), ['headers' => $headers]);
        $this->assertResponseStatusCodeSame(403);
        $client->request('PATCH', '/shares/'.$shares['Internal']->getId(), [
            'headers' => [...$headers, 'Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Renamed'],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // The owner may still get rid of it
        $client->request('DELETE', '/shares/'.$shares['Internal']->getId(), ['headers' => $headers]);
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * @return array<string, Asset>
     */
    private function createCategorizedAssets(Workspace $workspace, bool $public = true): array
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
            'None' => null,
        ] as $assetName => $category) {
            $assets[$assetName] = $this->createAsset([
                'workspace' => $workspace,
                'name' => $assetName,
                'public' => $public,
                'attributes' => null !== $category ? [[
                    'definition' => $definition,
                    'value' => $category,
                ]] : [],
            ]);
        }

        return $assets;
    }

    /**
     * @param array<string, Asset> $assets
     * @param array<string, int>   $expectedCodes
     */
    private function assertReadCodes(array $assets, array $expectedCodes, ?array $groups = null): void
    {
        $client = static::createClient();
        $codes = [];
        foreach (array_keys($expectedCodes) as $name) {
            $response = $client->request('GET', '/assets/'.$assets[$name]->getId(), [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID, groups: $groups),
                ],
            ]);
            $codes[$name] = $response->getStatusCode();
        }

        $this->assertSame($expectedCodes, $codes);
    }
}
