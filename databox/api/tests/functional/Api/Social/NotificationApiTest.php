<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\NotificationPreference;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Repository\NotificationDigestRepository;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * In-app notifications and their preferences, served by the notifier-bundle
 * controllers mounted in Databox: `/notifications*` and
 * `/notification-preferences`.
 *
 * Everything is scoped to the subscriber of the current user (created on
 * first use): a notification of another user is never listed and any action
 * on it is forbidden. Notifications are seeded directly in the database: the
 * delivery itself (channels, digests) is covered by the bundle unit tests.
 */
final class NotificationApiTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string UNKNOWN_ID = '1a2b3c4d-0000-4000-8000-000000000000';

    private function getSubscriber(string $userId): Subscriber
    {
        $em = self::getEntityManager();
        $subscriber = $em->getRepository(Subscriber::class)->findOneBy(['userId' => $userId]);
        if (null === $subscriber) {
            $subscriber = new Subscriber($userId);
            $em->persist($subscriber);
            $em->flush();
        }

        return $subscriber;
    }

    private function notify(string $userId, string $subject, bool $read = false, string $createdAt = 'now', string $topic = BuiltInTopic::ADMIN_MESSAGE, ?array $payload = null): Notification
    {
        $em = self::getEntityManager();

        $notification = new Notification($this->getSubscriber($userId), $topic, $payload ?? [
            'subject' => $subject,
            'body' => '<p>'.$subject.'</p>',
            'url' => '/assets/'.$subject,
        ]);
        (new \ReflectionProperty(Notification::class, 'createdAt'))->setValue($notification, new \DateTimeImmutable($createdAt));
        if ($read) {
            $notification->markAsRead();
        }
        $em->persist($notification);
        $em->flush();

        return $notification;
    }

    private function reload(Notification $notification): ?Notification
    {
        self::getEntityManager()->clear();

        return self::getEntityManager()->find(Notification::class, $notification->getId());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function endpointProvider(): iterable
    {
        yield 'list' => ['GET', '/notifications'];
        yield 'unread count' => ['GET', '/notifications/unread-count'];
        yield 'read' => ['POST', '/notifications/'.self::UNKNOWN_ID.'/read'];
        yield 'unread' => ['POST', '/notifications/'.self::UNKNOWN_ID.'/unread'];
        yield 'read all' => ['POST', '/notifications/read-all'];
        yield 'delete' => ['DELETE', '/notifications/'.self::UNKNOWN_ID];
        yield 'preferences' => ['GET', '/notification-preferences'];
        yield 'update preferences' => ['PUT', '/notification-preferences'];
        yield 'patch preferences' => ['PATCH', '/notification-preferences'];
    }

    /**
     * @dataProvider endpointProvider
     */
    public function testRequiresAuthentication(string $method, string $uri): void
    {
        $client = static::createClient();

        $client->request($method, $uri, ['json' => ['items' => []]]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListsOnlyOwnNotificationsNewestFirst(): void
    {
        $client = static::createClient();
        $this->notify(self::USER, 'old', read: true, createdAt: '-2 hours');
        $this->notify(self::USER, 'recent', createdAt: '-1 hour');
        $this->notify(self::OTHER, 'not-yours');

        $response = $client->request('GET', '/notifications', self::auth(self::USER));
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
        $this->assertSame(1, $data['unreadCount']);
        $this->assertSame(['recent', 'old'], array_column($data['items'], 'subject'));

        $item = $data['items'][0];
        $this->assertSame('<p>recent</p>', $item['content']);
        $this->assertSame(['uri' => '/assets/recent'], $item['data']);
        $this->assertFalse($item['read']);
        $this->assertNull($item['readAt']);
        $this->assertNotNull($item['createdAt']);
        // The raw topic and payload stay server-side
        $this->assertArrayNotHasKey('topic', $item);
        $this->assertArrayNotHasKey('payload', $item);
        $this->assertTrue($data['items'][1]['read']);
    }

    public function testUnreadFilterAndPagination(): void
    {
        $client = static::createClient();
        $this->notify(self::USER, 'n1', createdAt: '-3 hours');
        $this->notify(self::USER, 'n2', read: true, createdAt: '-2 hours');
        $this->notify(self::USER, 'n3', createdAt: '-1 hour');

        $data = $client->request('GET', '/notifications?unread=1', self::auth(self::USER))->toArray();
        $this->assertSame(2, $data['total']);
        $this->assertSame(['n3', 'n1'], array_column($data['items'], 'subject'));

        $data = $client->request('GET', '/notifications?limit=1&page=2', self::auth(self::USER))->toArray();
        $this->assertSame(3, $data['total']);
        $this->assertSame(['n2'], array_column($data['items'], 'subject'));
        $this->assertSame(2, $data['page']);
        $this->assertSame(1, $data['limit']);

        // Out-of-range values are clamped
        $data = $client->request('GET', '/notifications?limit=1000&page=-4', self::auth(self::USER))->toArray();
        $this->assertSame(100, $data['limit']);
        $this->assertSame(1, $data['page']);
    }

    public function testFirstCallOfANewUserCreatesItsSubscriber(): void
    {
        $client = static::createClient();

        $data = $client->request('GET', '/notifications', self::auth(self::USER))->toArray();
        $this->assertSame(['items' => [], 'total' => 0, 'page' => 1, 'limit' => 20, 'unreadCount' => 0], $data);
        $this->assertNotNull(self::getEntityManager()->getRepository(Subscriber::class)->findOneBy(['userId' => self::USER]));
    }

    public function testRenderedInTheRequestLocale(): void
    {
        $client = static::createClient();
        $this->notify(self::USER, 'comment', topic: 'discussion:new_comment', payload: [
            'author' => 'Alice',
            'object' => 'Sunset.jpg',
            'url' => '/assets/42#discussion-1',
        ]);

        $fr = $client->request('GET', '/notifications', self::auth(self::USER, [
            'headers' => ['Accept-Language' => 'fr'],
        ]))->toArray()['items'][0];
        $this->assertSame('Nouveau commentaire', $fr['subject']);
        $this->assertSame('Alice a commenté Sunset.jpg.', $fr['content']);
        $this->assertSame('/assets/42#discussion-1', $fr['data']['uri']);

        $en = $client->request('GET', '/notifications', self::auth(self::USER, [
            'headers' => ['Accept-Language' => 'en'],
        ]))->toArray()['items'][0];
        $this->assertSame('New comment', $en['subject']);
        $this->assertSame('Alice commented on Sunset.jpg.', $en['content']);
    }

    public function testUnreadCount(): void
    {
        $client = static::createClient();
        $this->notify(self::USER, 'a');
        $this->notify(self::USER, 'b');
        $this->notify(self::USER, 'c', read: true);
        $this->notify(self::OTHER, 'd');

        $data = $client->request('GET', '/notifications/unread-count', self::auth(self::USER))->toArray();
        $this->assertSame(['unreadCount' => 2], $data);
    }

    public function testMarkReadAndUnread(): void
    {
        $client = static::createClient();
        $notification = $this->notify(self::USER, 'toggle');

        $data = $client->request('POST', sprintf('/notifications/%s/read', $notification->getId()), self::auth(self::USER))->toArray();
        $this->assertSame($notification->getId(), $data['id']);
        $this->assertTrue($data['read']);
        $this->assertNotNull($data['readAt']);
        $this->assertTrue($this->reload($notification)->isRead());

        $data = $client->request('POST', sprintf('/notifications/%s/unread', $notification->getId()), self::auth(self::USER))->toArray();
        $this->assertFalse($data['read']);
        $this->assertNull($data['readAt']);
        $this->assertFalse($this->reload($notification)->isRead());
    }

    public function testReadingDiscardsThePendingEmailDigestOfTheTopic(): void
    {
        $client = static::createClient();
        $notification = $this->notify(self::USER, 'comment', topic: 'discussion:new_comment', payload: ['author' => 'A', 'object' => 'B']);
        $subscriberId = $this->getSubscriber(self::USER)->getId();
        /** @var NotificationDigestRepository $digests */
        $digests = self::getService(NotificationDigestRepository::class);
        $digests->append($subscriberId, 'discussion:new_comment', 'email', ['params' => []], new \DateTimeImmutable());
        $digests->append($subscriberId, 'asset:update', 'email', ['params' => []], new \DateTimeImmutable());

        $client->request('POST', sprintf('/notifications/%s/read', $notification->getId()), self::auth(self::USER));
        $this->assertResponseIsSuccessful();

        $topics = self::getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT topic FROM notifier_digest WHERE subscriber_id = ?',
            [$subscriberId],
        );
        $this->assertSame(['asset:update'], $topics, 'Only the digest of the read topic is dropped');
    }

    public function testCannotActOnTheNotificationOfAnotherUser(): void
    {
        $client = static::createClient();
        $notification = $this->notify(self::OTHER, 'private');
        $id = $notification->getId();

        $client->request('POST', sprintf('/notifications/%s/read', $id), self::auth(self::USER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('DELETE', '/notifications/'.$id, self::auth(self::USER));
        $this->assertResponseStatusCodeSame(403);

        $notification = $this->reload($notification);
        $this->assertNotNull($notification);
        $this->assertFalse($notification->isRead());

        $client->request('POST', sprintf('/notifications/%s/read', $id), self::auth(self::OTHER));
        $client->request('POST', sprintf('/notifications/%s/unread', $id), self::auth(self::USER));
        $this->assertResponseStatusCodeSame(403);
        $this->assertTrue($this->reload($notification)->isRead());
    }

    public function testUnknownNotification(): void
    {
        $client = static::createClient();

        foreach ([['POST', '/read'], ['POST', '/unread'], ['DELETE', '']] as [$method, $suffix]) {
            $client->request($method, '/notifications/'.self::UNKNOWN_ID.$suffix, self::auth(self::USER));
            $this->assertResponseStatusCodeSame(404);
        }
    }

    public function testDeleteOwnNotification(): void
    {
        $client = static::createClient();
        $notification = $this->notify(self::USER, 'bye');

        $client->request('DELETE', '/notifications/'.$notification->getId(), self::auth(self::USER));
        $this->assertResponseStatusCodeSame(204);
        $this->assertNull($this->reload($notification));
    }

    public function testReadAllOnlyTouchesOwnNotifications(): void
    {
        $client = static::createClient();
        $this->notify(self::USER, 'a');
        $this->notify(self::USER, 'b');
        $this->notify(self::USER, 'c', read: true);
        $foreign = $this->notify(self::OTHER, 'd');

        $data = $client->request('POST', '/notifications/read-all', self::auth(self::USER))->toArray();
        $this->assertSame(['markedAsRead' => 2], $data);

        $data = $client->request('GET', '/notifications/unread-count', self::auth(self::USER))->toArray();
        $this->assertSame(0, $data['unreadCount']);
        $this->assertFalse($this->reload($foreign)->isRead());

        // Nothing left to mark
        $data = $client->request('POST', '/notifications/read-all', self::auth(self::USER))->toArray();
        $this->assertSame(['markedAsRead' => 0], $data);
    }

    public function testDefaultPreferences(): void
    {
        $client = static::createClient();

        $items = $client->request('GET', '/notification-preferences', self::auth(self::USER))->toArray()['items'];
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'email', 'enabled' => true], $items);
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'in_app', 'enabled' => true], $items);
        foreach ($items as $item) {
            $this->assertTrue($item['enabled'], 'Every channel is enabled by default');
        }
    }

    public function testUpdatePreferencesIsScopedToTheCurrentUser(): void
    {
        $client = static::createClient();

        $items = $client->request('PUT', '/notification-preferences', self::auth(self::USER, [
            'json' => ['items' => [
                ['topic' => 'discussion:new_comment', 'channel' => 'email', 'enabled' => false],
                ['topic' => 'asset:update', 'channel' => 'in_app', 'enabled' => false],
            ]],
        ]))->toArray()['items'];
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'email', 'enabled' => false], $items);
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'in_app', 'enabled' => true], $items);
        $this->assertContains(['topic' => 'asset:update', 'channel' => 'in_app', 'enabled' => false], $items);

        // A single preference object, re-enabling one channel (PATCH)
        $items = $client->request('PATCH', '/notification-preferences', self::auth(self::USER, [
            'json' => ['topic' => 'asset:update', 'channel' => 'in_app', 'enabled' => true],
        ]))->toArray()['items'];
        $this->assertContains(['topic' => 'asset:update', 'channel' => 'in_app', 'enabled' => true], $items);
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'email', 'enabled' => false], $items);

        $this->assertSame(2, self::getEntityManager()->getRepository(NotificationPreference::class)->count([]), 'Updating a preference does not duplicate it');

        $items = $client->request('GET', '/notification-preferences', self::auth(self::OTHER))->toArray()['items'];
        $this->assertContains(['topic' => 'discussion:new_comment', 'channel' => 'email', 'enabled' => true], $items);
    }

    /**
     * @return iterable<string, array{array}>
     */
    public static function invalidPreferencesProvider(): iterable
    {
        yield 'no items' => [['foo' => 'bar']];
        yield 'missing enabled' => [['items' => [['topic' => 'asset:update', 'channel' => 'email']]]];
        yield 'unknown channel' => [['topic' => 'asset:update', 'channel' => 'pigeon', 'enabled' => true]];
    }

    /**
     * @dataProvider invalidPreferencesProvider
     */
    public function testInvalidPreferencesAreRejected(array $payload): void
    {
        $client = static::createClient();

        $client->request('PUT', '/notification-preferences', self::auth(self::USER, ['json' => $payload]));
        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(0, self::getEntityManager()->getRepository(NotificationPreference::class)->count([]));
    }

    public function testMalformedPreferencesBodyIsABadRequest(): void
    {
        $client = static::createClient();

        // The core JsonConverterSubscriber rejects the body before the
        // controller. It also runs on the error sub-request (which duplicates
        // the body), so the 400 escapes the kernel instead of being rendered.
        try {
            $client->request('PUT', '/notification-preferences', self::auth(self::USER, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => '{not json',
            ]));
            $this->assertResponseStatusCodeSame(400);
        } catch (BadRequestHttpException $e) {
            $this->assertStringContainsString('Invalid json body', $e->getMessage());
        }
        $this->assertSame(0, self::getEntityManager()->getRepository(NotificationPreference::class)->count([]));
    }
}
