<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use App\Entity\Core\Asset;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Entity\Workflow\WorkflowState;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Item actions of an asset: POST /assets/entities, follow/unfollow, metrics,
 * position, trigger-workflow, ES document, removal from a collection.
 */
final class AssetActionsTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    public function testResolveEntities(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $readable = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $readable->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        $secret = $this->createAsset(['ownerId' => self::OWNER]);
        $collection = $this->createCollection(['ownerId' => self::OTHER, 'name' => 'Mine']);
        $unknown = '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11';

        $response = $this->request('POST', '/assets/entities', self::OTHER, [
            'entities' => [
                '/assets/'.$readable->getId(),
                '/assets/'.$secret->getId(),
                '/collections/'.$collection->getId(),
                $unknown,
                '/users/'.self::OWNER,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $entities = $response->toArray()['entities'];
        $this->assertSame($readable->getId(), $entities['/assets/'.$readable->getId()]['id']);
        $this->assertSame(['notAllowed' => true], $entities['/assets/'.$secret->getId()]);
        $this->assertSame('Mine', $entities['/collections/'.$collection->getId()]['name']);
        $this->assertNull($entities[$unknown]);
        $this->assertArrayHasKey('/users/'.self::OWNER, $entities);
    }

    public function testResolveEntitiesAsAnonymous(): void
    {
        $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $public = $this->createAsset(['ownerId' => self::OWNER, 'public' => true]);
        $secret = $this->createAsset(['ownerId' => self::OWNER]);

        $entities = $this->request('POST', '/assets/entities', null, [
            'entities' => ['/assets/'.$public->getId(), '/assets/'.$secret->getId()],
        ])->toArray()['entities'];

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame($public->getId(), $entities['/assets/'.$public->getId()]['id']);
        $this->assertSame(['notAllowed' => true], $entities['/assets/'.$secret->getId()]);
    }

    public function testResolveEntitiesRejectsNonStringEntities(): void
    {
        $this->createOwnedWorkspace();

        $this->request('POST', '/assets/entities', self::OWNER, ['entities' => [42]]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testFollowAndUnfollow(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $uri = '/assets/'.$asset->getId();

        $this->assertSame([], $this->request('GET', $uri, self::OWNER)->toArray()['topicSubscriptions']);

        // All the events by default
        $this->request('POST', $uri.'/follow', self::OWNER, []);
        $this->assertResponseStatusCodeSame(201);
        $this->assertEqualsCanonicalizing(
            [Asset::EVENT_UPDATE, Asset::EVENT_DELETE, Asset::EVENT_NEW_COMMENT],
            $this->request('GET', $uri, self::OWNER)->toArray()['topicSubscriptions'],
        );

        // Then a single one
        $this->request('POST', $uri.'/unfollow', self::OWNER, ['key' => Asset::EVENT_NEW_COMMENT]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertEqualsCanonicalizing(
            [Asset::EVENT_UPDATE, Asset::EVENT_DELETE],
            $this->request('GET', $uri, self::OWNER)->toArray()['topicSubscriptions'],
        );

        $this->request('POST', $uri.'/unfollow', self::OWNER, []);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([], $this->request('GET', $uri, self::OWNER)->toArray()['topicSubscriptions']);

        // Subscriptions are per user
        $this->request('POST', $uri.'/follow', self::OWNER, ['key' => Asset::EVENT_UPDATE]);
        $this->assertSame([], $this->request('GET', $uri, self::ADMIN)->toArray()['topicSubscriptions']);
    }

    public function testFollowRequiresReadingTheAsset(): void
    {
        $workspace = $this->createOwnedWorkspace(self::OWNER, ['public' => true]);
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $secret = $this->createAsset(['ownerId' => self::OWNER]);
        $public = $this->createAsset(['ownerId' => self::OWNER, 'public' => true]);

        $this->request('POST', '/assets/'.$secret->getId().'/follow', self::OTHER, []);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', '/assets/'.$public->getId().'/follow', self::OTHER, []);
        $this->assertResponseStatusCodeSame(201);

        // A subscription needs a user (denied by the processor, hence a 403)
        $response = $this->request('POST', '/assets/'.$public->getId().'/follow', null, []);
        $this->assertResponseStatusCodeSame(403);
        $this->assertStringContainsString('must be authenticated', $this->errorMessage($response));
    }

    /**
     * @return iterable<string, array{string, string, ?array}>
     */
    public static function postActionOnUnknownAssetProvider(): iterable
    {
        yield 'follow' => ['follow', self::OWNER, []];
        yield 'unfollow' => ['unfollow', self::OWNER, []];
        yield 'quarantine-bypass' => ['quarantine-bypass', self::ADMIN, null];
    }

    /**
     * @dataProvider postActionOnUnknownAssetProvider
     */
    public function testPostActionOnAnUnknownAssetIs404(string $action, string $userId, ?array $payload): void
    {
        $this->markTestIncomplete('BUG: API Platform does not answer 404 when the item of a POST operation is not found: the processors get a null asset (FollowProcessor/UnfollowProcessor deny with a 403, BypassQuarantineProcessor crashes with a 500 "getSource() on null" for an admin, src/Api/Processor/BypassQuarantineProcessor.php:37).');

        $this->createOwnedWorkspace();

        $this->request('POST', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11/'.$action, $userId, $payload);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFollowAnUnknownEventIsRejected(): void
    {
        $this->markTestIncomplete('BUG: FollowEventResolverTrait::resolveEvents() throws a plain \InvalidArgumentException for an unknown key, answered as a 500 instead of a 400 (src/Api/Processor/FollowEventResolverTrait.php:26).');

        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);

        $this->request('POST', '/assets/'.$asset->getId().'/follow', self::OWNER, ['key' => 'asset:unknown']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testMetricsAreReadFromTheAnalyticsCache(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $trackingId = 'tracking-'.uniqid();
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setTrackingId($trackingId);
        self::getEntityManager()->flush();

        // Matomo is not reachable in tests: the metrics are served from the cache
        /** @var CacheInterface $cache */
        $cache = self::getContainer()->get('analytics.cache');
        $cache->delete('metrics_'.$trackingId);
        $cache->get('metrics_'.$trackingId, function (ItemInterface $item): array {
            return ['nb_impressions' => 12, 'nb_interactions' => 3];
        });

        try {
            $response = $this->request('GET', '/assets/'.$asset->getId().'/metrics', self::OWNER);
            $this->assertResponseIsSuccessful();
            $this->assertSame(['nb_impressions' => 12, 'nb_interactions' => 3], $response->toArray());

            $this->request('GET', '/assets/'.$asset->getId().'/metrics', self::OTHER);
            $this->assertResponseStatusCodeSame(403);

            $this->request('GET', '/assets/7b2d1d6e-0f9a-4b1c-9c41-1f0d6d0c2f11/metrics', self::OWNER);
            $this->assertResponseStatusCodeSame(404);
        } finally {
            $cache->delete('metrics_'.$trackingId);
        }
    }

    public function testSetPositionValidation(): void
    {
        $this->createOwnedWorkspace();
        $collection = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $collection->getId()]);
        $uri = '/assets/'.$asset->getId().'/position';

        $this->request('PUT', $uri, self::OWNER, ['destination' => '/collections/'.$collection->getId(), 'position' => -1]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('PUT', $uri, self::OWNER, ['position' => 0]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('PUT', $uri, self::OWNER, ['destination' => '/collections/'.$collection->getId()]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('PUT', $uri, null, ['destination' => '/collections/'.$collection->getId(), 'position' => 0]);
        $this->assertResponseStatusCodeSame(401);

        $this->request('PUT', $uri, self::OWNER, ['destination' => '/collections/'.$collection->getId(), 'position' => 0]);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testTriggerWorkflowStartsTheIngestWorkflow(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'no_flush' => true]);
        $asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        self::getEntityManager()->flush();

        // Reading is not enough
        $this->request('PUT', '/assets/'.$asset->getId().'/trigger-workflow', self::OTHER, []);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame([], $this->findWorkflowStates($asset->getId()));

        $data = $this->request('PUT', '/assets/'.$asset->getId().'/trigger-workflow', self::OWNER, [])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame($asset->getId(), $data['id']);

        $states = $this->findWorkflowStates($asset->getId());
        $this->assertCount(1, $states);
        $this->assertSame(self::OWNER, $states[0]->getInitiatorId());

        $this->request('PUT', '/assets/'.$asset->getId().'/trigger-workflow', self::OWNER, []);
        $this->assertCount(2, $this->findWorkflowStates($asset->getId()));
    }

    public function testElasticsearchDocumentIsReservedToTechUsers(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $uri = '/assets/'.$asset->getId();

        // Even the owner of the asset
        $this->request('GET', $uri.'/es-document', self::OWNER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('POST', $uri.'/es-document-sync', self::OWNER, []);
        $this->assertResponseStatusCodeSame(403);

        $this->request('POST', $uri.'/es-document-sync', self::ADMIN, []);
        $this->assertResponseStatusCodeSame(201);

        $data = $this->request('GET', $uri.'/es-document', self::ADMIN)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertTrue($data['synced']);
        $this->assertSame($asset->getId(), $data['data']['_id']);
        $this->assertSame(self::OWNER, $data['data']['_source']['ownerId']);
    }

    public function testElasticsearchDocumentReportsAnOutdatedDocument(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $uri = '/assets/'.$asset->getId();
        $this->request('POST', $uri.'/es-document-sync', self::ADMIN, []);

        // Changed without the ES listeners (as a bulk SQL update would)
        self::getEntityManager()->getConnection()->executeStatement(
            'UPDATE asset SET privacy = :p WHERE id = :id',
            ['p' => WorkspaceItemPrivacyInterface::PUBLIC, 'id' => $asset->getId()],
        );

        $data = $this->request('GET', $uri.'/es-document', self::ADMIN)->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertFalse($data['synced']);

        $this->request('POST', $uri.'/es-document-sync', self::ADMIN, []);
        $data = $this->request('GET', $uri.'/es-document', self::ADMIN)->toArray();
        $this->assertTrue($data['synced']);
    }

    public function testRemoveFromACollection(): void
    {
        $this->createOwnedWorkspace();
        $reference = $this->createCollection(['ownerId' => self::OWNER]);
        $linked = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $reference->getId()]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());

        $this->request('DELETE', '/assets/'.$asset->getId().'/collections/'.$linked->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(204);

        $this->assertSame([$reference->getId()], $this->getCollectionIds($asset->getId()));
        $this->assertNull($this->reloadAsset($asset->getId())->getDeletedAt());

        // Already removed: nothing to do
        $this->request('DELETE', '/assets/'.$asset->getId().'/collections/'.$linked->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testRemoveFromACollectionRequiresEditOnTheCollection(): void
    {
        $workspace = $this->createOwnedWorkspace();
        $this->addUserOnWorkspace(self::OTHER, $workspace->getId());
        $linked = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OTHER]);
        $this->addAssetToCollection($linked->getId(), $asset->getId());

        $this->request('DELETE', '/assets/'.$asset->getId().'/collections/'.$linked->getId(), self::OTHER);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame([$linked->getId()], $this->getCollectionIds($asset->getId()));
    }

    public function testRemoveFromTheReferenceCollectionIsRejected(): void
    {
        $this->markTestIncomplete('BUG: RemoveAssetFromCollectionProcessor throws a plain \InvalidArgumentException for the reference collection, answered as a 500 instead of a 400 (src/Api/Processor/RemoveAssetFromCollectionProcessor.php:35).');

        $this->createOwnedWorkspace();
        $reference = $this->createCollection(['ownerId' => self::OWNER]);
        $asset = $this->createAsset(['ownerId' => self::OWNER, 'collectionId' => $reference->getId()]);

        $this->request('DELETE', '/assets/'.$asset->getId().'/collections/'.$reference->getId(), self::OWNER);
        $this->assertResponseStatusCodeSame(400);
        $this->assertSame([$reference->getId()], $this->getCollectionIds($asset->getId()));
    }

    /**
     * @return WorkflowState[]
     */
    private function findWorkflowStates(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->getRepository(WorkflowState::class)->findBy(['asset' => $assetId]);
    }

    /**
     * @return string[]
     */
    private function getCollectionIds(string $assetId): array
    {
        $em = self::getEntityManager();
        $em->clear();

        return array_map(
            fn (CollectionAsset $ca): string => $ca->getCollection()->getId(),
            $em->getRepository(CollectionAsset::class)->findBy(['asset' => $assetId]),
        );
    }
}
