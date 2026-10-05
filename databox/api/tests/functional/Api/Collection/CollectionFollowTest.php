<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * POST /collections/{id}/follow and /collections/{id}/unfollow.
 */
final class CollectionFollowTest extends AbstractDataboxTestCase
{
    use CollectionTestTrait;

    private const array ALL_EVENTS = [
        Collection::EVENT_ASSET_ADD,
        Collection::EVENT_ASSET_REMOVE,
        Collection::EVENT_ASSET_NEW_COMMENT,
        Collection::EVENT_ASSET_UPDATE,
    ];

    public function testFollowAndUnfollowAllEvents(): void
    {
        $collection = $this->createReadableCollection();

        $response = $this->request('POST', '/collections/'.$collection->getId().'/follow', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['id' => $collection->getId()]);
        $this->assertEqualsCanonicalizing(self::ALL_EVENTS, $response->toArray()['topicSubscriptions']);
        $this->assertEqualsCanonicalizing(self::ALL_EVENTS, $this->getSubscriptions($collection, self::USER));

        // Following twice is harmless
        $this->request('POST', '/collections/'.$collection->getId().'/follow', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertEqualsCanonicalizing(self::ALL_EVENTS, $this->getSubscriptions($collection, self::USER));

        // Subscriptions are personal
        $this->assertSame([], $this->getSubscriptions($collection, self::OTHER));

        $response = $this->request('POST', '/collections/'.$collection->getId().'/unfollow', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([], $response->toArray()['topicSubscriptions']);
        $this->assertSame([], $this->getSubscriptions($collection, self::USER));

        // Unfollowing a collection which is not followed is harmless
        $this->request('POST', '/collections/'.$collection->getId().'/unfollow', self::OTHER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testFollowASingleEvent(): void
    {
        $collection = $this->createReadableCollection();

        $this->request('POST', '/collections/'.$collection->getId().'/follow', self::USER, [
            'json' => ['key' => Collection::EVENT_ASSET_ADD],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([Collection::EVENT_ASSET_ADD], $this->getSubscriptions($collection, self::USER));

        $this->request('POST', '/collections/'.$collection->getId().'/follow', self::USER, [
            'json' => [],
        ]);
        $this->request('POST', '/collections/'.$collection->getId().'/unfollow', self::USER, [
            'json' => ['key' => Collection::EVENT_ASSET_UPDATE],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertEqualsCanonicalizing([
            Collection::EVENT_ASSET_ADD,
            Collection::EVENT_ASSET_REMOVE,
            Collection::EVENT_ASSET_NEW_COMMENT,
        ], $this->getSubscriptions($collection, self::USER));
    }

    public function testFollowAnInvalidEvent(): void
    {
        $this->markTestIncomplete('BUG: FollowEventResolverTrait::resolveEvents() (src/Api/Processor/FollowEventResolverTrait.php:26) throws a plain \InvalidArgumentException for an unknown key: 500 instead of 400');

        $collection = $this->createReadableCollection();

        foreach (['follow', 'unfollow'] as $action) {
            $this->request('POST', '/collections/'.$collection->getId().'/'.$action, self::USER, [
                'json' => ['key' => 'not-an-event'],
            ]);
            $this->assertResponseStatusCodeSame(400, $action);
        }
    }

    public function testFollowRequiresRead(): void
    {
        $collection = $this->createReadableCollection();
        $secret = $this->createCollection(['workspace' => $collection->getWorkspace(), 'name' => 'Secret']);

        foreach (['follow', 'unfollow'] as $action) {
            $this->request('POST', '/collections/'.$secret->getId().'/'.$action, self::USER, [
                'json' => [],
            ]);
            $this->assertResponseStatusCodeSame(403, $action);

            // Not a member of the workspace
            $this->request('POST', '/collections/'.$collection->getId().'/'.$action, self::OTHER, [
                'json' => [],
            ]);
            $this->assertResponseStatusCodeSame(403, $action);

            // An unknown collection is denied (no 404 for these operations)
            $this->request('POST', '/collections/00000000-0000-4000-8000-000000000000/'.$action, self::USER, [
                'json' => [],
            ]);
            $this->assertResponseStatusCodeSame(403, $action);
        }
        $this->assertSame([], self::getService(SubscriptionManager::class)->getSubscribedEvents(
            self::USER,
            Collection::OBJECT_TYPE,
            $secret->getId(),
        ));
    }

    public function testAnonymousCannotFollow(): void
    {
        $workspace = $this->createTestWorkspace(['public' => true]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Public']);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC);

        foreach (['follow', 'unfollow'] as $action) {
            $this->request('POST', '/collections/'.$collection->getId().'/'.$action, self::ANONYMOUS, [
                'json' => [],
            ]);
            // Denied by SecurityAwareTrait::getStrictUser() (403, not 401)
            $this->assertResponseStatusCodeSame(403, $action);
            $this->assertJsonContains(['hydra:description' => 'User must be authenticated']);
        }
    }

    private function createReadableCollection(): Collection
    {
        $workspace = $this->createTestWorkspace(['members' => [self::USER]]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Followed']);
        $this->setCollectionPrivacy($collection, Privacy::PRIVATE_IN_WORKSPACE);

        return $collection;
    }

    /**
     * @return string[]
     */
    private function getSubscriptions(Collection $collection, string $userId): array
    {
        if (self::OTHER === $userId) {
            // OTHER is not allowed in the workspace, and the subscriptions of the reader only are exposed
            $this->addUserOnWorkspace(self::OTHER, $collection->getWorkspaceId());
        }

        $response = $this->request('GET', '/collections/'.$collection->getId(), $userId);
        $this->assertResponseStatusCodeSame(200);

        return $response->toArray()['topicSubscriptions'];
    }
}
