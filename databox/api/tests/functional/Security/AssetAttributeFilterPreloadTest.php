<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Security\AssetAttributeFilterChecker;
use App\Security\Voter\AbstractVoter;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The decisions taken at once on a list of assets are the ones taken asset by asset,
 * with a number of queries that does not grow with the list.
 */
class AssetAttributeFilterPreloadTest extends AbstractDataboxTestCase
{
    #[DataProvider('getConditionsDataSet')]
    public function testPreloadTakesTheSameDecisions(string $condition): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assetIds = $this->createAssets($workspace, 6);
        $collection = $this->createCollection([
            'workspace' => $workspace,
            'public' => true,
        ]);
        foreach (array_slice($assetIds, 0, 3) as $assetId) {
            $this->addAssetToCollection($collection->getId(), $assetId);
        }

        self::getAttributeFilterManager()->saveRule(
            self::getEntityManager()->find(Workspace::class, $workspace->getId()),
            [KeycloakClientTestMock::USER_UID],
            [],
            str_replace('{collection}', $collection->getId(), $condition),
        );

        $user = new JwtUser('JWT', KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::USER_UID, ['ROLE_USER']);
        self::getService(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $expected = $this->getDecisions($assetIds, false);
        $this->assertContains(true, $expected);
        $this->assertSame($expected, $this->getDecisions($assetIds, true));
    }

    public static function getConditionsDataSet(): array
    {
        return [
            'attribute' => ['category = "press"'],
            'missing attribute' => ['category IS MISSING'],
            'collection' => ['@collection = "{collection}"'],
            'direct collection' => ['NOT (@directCollection = "{collection}")'],
            'attribute or collection' => ['category = "internal" OR @collection = "{collection}"'],
        ];
    }

    public function testInvalidConditionDeniesEveryAsset(): void
    {
        $workspace = $this->createWorkspace(['public' => true]);
        $assetIds = $this->createAssets($workspace, 3);
        self::getAttributeFilterManager()->saveRule(
            self::getEntityManager()->find(Workspace::class, $workspace->getId()),
            [KeycloakClientTestMock::USER_UID],
            [],
            'unknown_field = "press"',
        );

        $user = new JwtUser('JWT', KeycloakClientTestMock::USER_UID, KeycloakClientTestMock::USER_UID, ['ROLE_USER']);
        self::getService(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $this->assertSame([false, false, false], $this->getDecisions($assetIds, true));
    }

    public function testQueriesOfTheRulesDoNotGrowWithTheAssets(): void
    {
        $this->assertQueriesOfTheRulesDoNotGrow(function (Workspace $workspace, int $count): array {
            $collection = $this->createCollection([
                'workspace' => $workspace,
                'public' => true,
            ]);

            return $this->createAssets($workspace, $count, $collection->getId(), 'press');
        });
    }

    public function testQueriesOfTheRulesDoNotGrowWithTheMembersOfAStory(): void
    {
        $this->assertQueriesOfTheRulesDoNotGrow(function (Workspace $workspace, int $count): array {
            $em = self::getEntityManager();
            $story = $this->createAsset([
                'workspace' => $workspace,
                'name' => 'Story',
                'public' => true,
                'no_flush' => true,
            ]);
            $storyCollection = new Collection();
            $storyCollection->setWorkspace($workspace);
            $storyCollection->setOwnerId($story->getOwnerId());
            $em->persist($storyCollection);
            $story->setStoryCollection($storyCollection);
            $em->flush();

            return $this->createAssets($workspace, $count, $storyCollection->getId(), 'press');
        });
    }

    /**
     * @param callable(Workspace, int): string[] $createAssets
     */
    private function assertQueriesOfTheRulesDoNotGrow(callable $createAssets): void
    {
        // Assets voted on by POST /assets/prepare-delete, by workspace
        $lists = [];
        foreach ([5, 20] as $count) {
            $workspace = $this->createWorkspace([
                'public' => true,
                'slug' => 'ws-'.$count,
            ]);
            $lists[$workspace->getId()] = $createAssets($workspace, $count);
        }

        $client = static::createClient();
        $countQueries = function (array $assetIds) use ($client): int {
            $client->enableProfiler();
            $client->request('POST', '/assets/prepare-delete', [
                'headers' => [
                    'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
                ],
                'json' => ['ids' => $assetIds],
            ]);
            $this->assertResponseIsSuccessful();

            return $client->getProfile()->getCollector('db')->getQueryCount();
        };

        // The first request of the kernel runs queries of its own
        $countQueries(reset($lists));

        $overheads = array_map($countQueries, $lists);
        foreach (array_keys($lists) as $workspaceId) {
            self::getAttributeFilterManager()->saveRule(
                self::getEntityManager()->find(Workspace::class, $workspaceId),
                [KeycloakClientTestMock::USER_UID],
                [],
                'category = "press"',
            );
        }
        foreach ($lists as $workspaceId => $assetIds) {
            $overheads[$workspaceId] = $countQueries($assetIds) - $overheads[$workspaceId];
        }

        [$small, $large] = array_values($overheads);
        $this->assertLessThanOrEqual($small, $large, sprintf('5 assets: %+d queries, 20 assets: %+d queries', $small, $large));
    }

    /**
     * @return string[]
     */
    private function createAssets(Workspace $workspace, int $count, ?string $collectionId = null, ?string $category = null): array
    {
        $definition = $this->createAttributeDefinition([
            'workspace' => $workspace,
            'name' => 'Category',
            'slug' => 'category',
        ]);

        $categories = ['press', 'internal', null];
        $assets = [];
        for ($i = 0; $i < $count; ++$i) {
            $value = $category ?? $categories[$i % count($categories)];
            $assets[] = $this->createAsset([
                'workspace' => $workspace,
                'name' => 'Asset '.$i,
                'public' => true,
                'collectionId' => $collectionId,
                'attributes' => null !== $value ? [[
                    'definition' => $definition,
                    'value' => $value,
                ]] : [],
                'no_flush' => true,
            ]);
        }
        self::getEntityManager()->flush();

        return array_map(static fn (Asset $asset): string => $asset->getId(), $assets);
    }

    /**
     * @param string[] $assetIds
     *
     * @return bool[]
     */
    private function getDecisions(array $assetIds, bool $preload): array
    {
        $em = self::getEntityManager();
        $em->clear();
        $checker = self::getService(AssetAttributeFilterChecker::class);
        $checker->clearCache();

        $assets = array_map(fn (string $id): Asset => $em->find(Asset::class, $id), $assetIds);
        if ($preload) {
            $checker->preloadForCurrentUser($assets);
        }

        return array_map(
            static fn (Asset $asset): bool => self::getService(Security::class)->isGranted(AbstractVoter::READ, $asset),
            $assets,
        );
    }
}
