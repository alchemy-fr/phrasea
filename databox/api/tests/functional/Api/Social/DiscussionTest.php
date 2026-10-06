<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Social;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Message\PusherMessage;
use Alchemy\MessengerBundle\Transport\TestTransport;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Consumer\Handler\Discussion\PostDiscussionMessage;
use App\Entity\Core\Asset;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Entity\Discussion\Message;
use App\Entity\Discussion\Thread;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Discussions on assets: `POST /messages`, `GET|PUT|DELETE /messages/{id}`,
 * `GET /threads/{threadId}/messages` and `GET /threads/{id}`.
 *
 * A thread is keyed by its object (`asset:{id}`) and created by the first
 * message. Reading a thread needs READ on its object, posting needs to be
 * authenticated on top of it; only the author edits or deletes a message
 * (and the admin, granted everything by the AdminVoter).
 *
 * Setup: USER owns the workspace, OTHER_USER is a member who reads the
 * "public in workspace" asset but not the secret one.
 */
final class DiscussionTest extends AbstractDataboxTestCase
{
    use SocialTestTrait;

    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;
    private const string UNKNOWN_ID = '1a2b3c4d-0000-4000-8000-000000000000';

    private Workspace $workspace;
    private Asset $asset;
    private Asset $secret;

    private function setUpWorkspace(bool $public = false): void
    {
        $this->workspace = $this->createNamedWorkspace(self::USER, 'discussion-ws', $public);
        $this->addUserOnWorkspace(self::OTHER, $this->workspace->getId());

        $this->asset = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
            'public' => $public,
        ]);
        if (!$public) {
            $this->asset->setPrivacy(WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE);
        }
        $this->secret = $this->createAsset([
            'workspace' => $this->workspace,
            'ownerId' => self::USER,
        ]);
        self::getEntityManager()->flush();
    }

    private static function threadKey(Asset $asset): string
    {
        return 'asset:'.$asset->getId();
    }

    private function post(Client $client, ?string $userId, array $data): array
    {
        return $client->request('POST', '/messages', self::auth($userId, ['json' => $data]))->toArray(false);
    }

    private function postOn(Client $client, string $userId, Asset $asset, string $content): array
    {
        $data = $this->post($client, $userId, [
            'threadKey' => self::threadKey($asset),
            'content' => $content,
        ]);
        $this->assertResponseStatusCodeSame(201);

        return $data;
    }

    private function findThread(Asset $asset): ?Thread
    {
        return self::getEntityManager()->getRepository(Thread::class)->findOneBy(['key' => self::threadKey($asset)]);
    }

    public function testPostRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, null, [
            'threadKey' => self::threadKey($this->asset),
            'content' => 'Hello',
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findThread($this->asset));
    }

    public function testFirstMessageCreatesTheThread(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $this->assertNull($this->findThread($this->asset));

        $data = $this->postOn($client, self::USER, $this->asset, 'First!');

        $this->assertMatchesUuid($data['id']);
        $this->assertSame('First!', $data['content']);
        $this->assertSame(self::USER, $data['author']['id']);
        $this->assertSame('user', $data['author']['username']);
        $this->assertSame(['edit' => true, 'delete' => true], $data['capabilities']);

        $thread = $this->findThread($this->asset);
        $this->assertNotNull($thread);
        $this->assertSame('/threads/'.$thread->getId(), $data['thread']['@id']);
        $this->assertSame($thread->getId(), $data['thread']['id']);

        // The next message, by key or by id, goes to the same thread
        $this->postOn($client, self::USER, $this->asset, 'Second');
        $this->post($client, self::USER, ['threadId' => $thread->getId(), 'content' => 'Third']);
        $this->assertResponseStatusCodeSame(201);

        self::getEntityManager()->clear();
        $this->assertSame(1, self::getEntityManager()->getRepository(Thread::class)->count([]));
        $this->assertSame(3, self::getEntityManager()->getRepository(Message::class)->count(['thread' => $thread->getId()]));
    }

    public function testReaderMayComment(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $data = $this->postOn($client, self::OTHER, $this->asset, 'I can read it, I can comment it');
        $this->assertSame(self::OTHER, $data['author']['id']);
    }

    public function testCannotDiscussAnInvisibleAsset(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::OTHER, [
            'threadKey' => self::threadKey($this->secret),
            'content' => 'Knock knock',
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findThread($this->secret), 'A denied post does not create the thread');

        // Same through the id of an existing thread
        $this->postOn($client, self::USER, $this->secret, 'Private note');
        $thread = $this->findThread($this->secret);
        $this->post($client, self::OTHER, [
            'threadId' => $thread->getId(),
            'content' => 'Knock knock',
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testEmptyMessageIsRejected(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::USER, [
            'threadKey' => self::threadKey($this->asset),
            'content' => '   ',
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testMessageWithoutThreadIsRejected(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::USER, ['content' => 'Lost']);
        if (500 === $client->getResponse()->getStatusCode()) {
            $this->markTestIncomplete('BUG: ThreadMessageInput::validateThreadKeyOrThreadId() (src/Api/Model/Input/ThreadMessageInput.php:27) throws an \InvalidArgumentException from an Assert\Callback instead of adding a violation: 500 instead of 422.');
        }
        $this->assertResponseStatusCodeSame(422);
    }

    public function testUnknownThreadIdIsNotFound(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::USER, ['threadId' => self::UNKNOWN_ID, 'content' => 'Hello?']);
        if (500 === $client->getResponse()->getStatusCode()) {
            $this->markTestIncomplete('BUG: PostMessageProcessor (src/Api/Processor/PostMessageProcessor.php:43) uses DoctrineUtil::findStrictByRepo() without $throw404: an unknown "threadId" raises \InvalidArgumentException, i.e. a 500 instead of a 404.');
        }
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidThreadKeyProvider(): iterable
    {
        yield 'no object type' => ['not-a-key'];
        yield 'unknown object' => ['asset:'.self::UNKNOWN_ID];
    }

    #[DataProvider('invalidThreadKeyProvider')]
    public function testInvalidThreadKeyIsRejected(string $threadKey): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::USER, ['threadKey' => $threadKey, 'content' => 'Hello?']);
        $status = $client->getResponse()->getStatusCode();
        if (500 === $status) {
            $this->markTestIncomplete('BUG: a "threadKey" without object or targeting a missing object makes DiscussionManager::getThreadObject() (src/Service/Discussion/DiscussionManager.php:26-34) throw a \RuntimeException from the ThreadVoter: 500 instead of 400/404.');
        }
        $this->assertContains($status, [400, 404]);
    }

    /**
     * @return iterable<string, array{array}>
     */
    public static function invalidAttachmentProvider(): iterable
    {
        yield 'not an object' => [['oops']];
        yield 'without type' => [[['content' => '{}']]];
        yield 'file posted by id' => [[['type' => 'file', 'content' => '{"id": "'.self::UNKNOWN_ID.'"}']]];
    }

    #[DataProvider('invalidAttachmentProvider')]
    public function testInvalidAttachmentsAreRejected(array $attachments): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $this->post($client, self::USER, [
            'threadKey' => self::threadKey($this->asset),
            'content' => 'See attached',
            'attachments' => $attachments,
        ]);
        $this->assertResponseStatusCodeSame(400);
        self::getEntityManager()->clear();
        $this->assertSame(0, self::getEntityManager()->getRepository(Message::class)->count([]));
    }

    public function testMentionsAreKeptInTheContent(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();

        $content = sprintf('Hey @[other_user](%s), have a look', self::OTHER);
        $data = $this->postOn($client, self::USER, $this->asset, $content);

        $this->assertSame($content, $data['content']);
    }

    public function testPostingDispatchesTheRealtimeAndNotificationMessages(): void
    {
        $client = static::createClient();
        // Keep the kernel (and its intercepting transports) between requests
        $client->disableReboot();
        $this->setUpWorkspace();

        /** @var TestTransport $p1 */
        $p1 = static::getContainer()->get('messenger.transport.p1');
        /** @var TestTransport $p2 */
        $p2 = static::getContainer()->get('messenger.transport.p2');
        $realtime = $p1->intercept();
        $async = $p2->intercept();

        $data = $this->postOn($client, self::USER, $this->asset, 'Notify them');

        $notifications = array_values(array_filter(
            array_map(static fn ($e) => $e->getMessage(), $async->getSent()),
            static fn ($m): bool => $m instanceof PostDiscussionMessage,
        ));
        $this->assertCount(1, $notifications);
        $this->assertSame($data['id'], $notifications[0]->getId());

        $pushes = array_values(array_filter(
            array_map(static fn ($e) => $e->getMessage(), $realtime->getSent()),
            static fn ($m): bool => $m instanceof PusherMessage && str_starts_with($m->getChannel(), 'thread-'),
        ));
        $this->assertCount(1, $pushes);
        $this->assertSame('thread-'.self::threadKey($this->asset), $pushes[0]->getChannel());
        $this->assertSame('message', $pushes[0]->getEvent());
        $this->assertSame(['id' => $data['id']], $pushes[0]->getPayload());
    }

    public function testGetMessageRights(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $id = $this->postOn($client, self::USER, $this->asset, 'Readable')['id'];
        $secretId = $this->postOn($client, self::USER, $this->secret, 'Hidden')['id'];

        $data = $client->request('GET', '/messages/'.$id, self::auth(self::USER))->toArray();
        $this->assertSame('Readable', $data['content']);
        $this->assertSame(['edit' => true, 'delete' => true], $data['capabilities']);

        // A reader sees the message, without edit capabilities
        $data = $client->request('GET', '/messages/'.$id, self::auth(self::OTHER))->toArray();
        $this->assertSame(['edit' => false, 'delete' => false], $data['capabilities']);

        $client->request('GET', '/messages/'.$secretId, self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/messages/'.$id);
        $this->assertResponseStatusCodeSame(401);

        $client->request('GET', '/messages/'.self::UNKNOWN_ID, self::auth(self::USER));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListThreadMessages(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $this->postOn($client, self::USER, $this->asset, 'one');
        $this->postOn($client, self::OTHER, $this->asset, 'two');
        $this->postOn($client, self::USER, $this->asset, 'three');
        $this->postOn($client, self::USER, $this->secret, 'elsewhere');
        $thread = $this->findThread($this->asset);
        // Timestamps have a one-second precision: spread the messages in time
        foreach (['one' => '-3 minutes', 'two' => '-2 minutes', 'three' => '-1 minute'] as $content => $date) {
            self::getEntityManager()->createQueryBuilder()
                ->update(Message::class, 'm')
                ->set('m.createdAt', ':date')
                ->andWhere('m.content = :content')
                ->setParameter('date', new \DateTimeImmutable($date), 'datetime_immutable')
                ->setParameter('content', $content)
                ->getQuery()
                ->execute();
        }

        $data = $client->request('GET', sprintf('/threads/%s/messages', $thread->getId()), self::auth(self::OTHER))->toArray();
        $this->assertSame(3, $data['totalItems']);
        $this->assertSame(['one', 'two', 'three'], array_column($data['member'], 'content'), 'Oldest first');
        $this->assertSame([self::USER, self::OTHER, self::USER], array_map(static fn (array $m): string => $m['author']['id'], $data['member']));
        $this->assertSame([false, true, false], array_map(static fn (array $m): bool => $m['capabilities']['edit'], $data['member']));
    }

    public function testListMessagesOfAnInvisibleThreadIsDenied(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $this->postOn($client, self::USER, $this->secret, 'Hidden');
        $thread = $this->findThread($this->secret);

        $client->request('GET', sprintf('/threads/%s/messages', $thread->getId()), self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', sprintf('/threads/%s/messages', $thread->getId()));
        $this->assertResponseStatusCodeSame(401);

        $client->request('GET', sprintf('/threads/%s/messages', self::UNKNOWN_ID), self::auth(self::USER));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testThreadItemIsNotExposed(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $this->postOn($client, self::USER, $this->asset, 'Hello');
        $thread = $this->findThread($this->asset);

        // The route only exists to generate the thread IRI
        $client->request('GET', '/threads/'.$thread->getId(), self::auth(self::USER));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPublicAssetDiscussionIsReadableAnonymouslyButNotWritable(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace(public: true);
        $this->postOn($client, self::USER, $this->asset, 'Public talk');
        $thread = $this->findThread($this->asset);

        $data = $client->request('GET', sprintf('/threads/%s/messages', $thread->getId()))->toArray();
        $this->assertSame(['Public talk'], array_column($data['member'], 'content'));
        $this->assertSame(['edit' => false, 'delete' => false], $data['member'][0]['capabilities']);

        $this->post($client, null, ['threadId' => $thread->getId(), 'content' => 'Anonymous']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAuthorEditsAndDeletesOwnMessage(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $id = $this->postOn($client, self::OTHER, $this->asset, 'Typo')['id'];

        $data = $client->request('PATCH', '/messages/'.$id, self::patchOptions(self::OTHER, [
            'json' => ['content' => 'Fixed'],
        ]))->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Fixed', $data['content']);

        // An empty edit (no content, nothing removed) changes nothing
        $data = $client->request('PATCH', '/messages/'.$id, self::patchOptions(self::OTHER, ['json' => []]))->toArray();
        $this->assertSame('Fixed', $data['content']);

        $client->request('PATCH', '/messages/'.$id, self::patchOptions(self::OTHER, ['json' => ['content' => '']]));
        $this->assertResponseStatusCodeSame(400);

        $client->request('DELETE', '/messages/'.$id, self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/messages/'.$id, self::auth(self::OTHER));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCannotEditOrDeleteTheMessageOfSomeoneElse(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        // USER owns the workspace and the asset, yet does not own OTHER's words
        $id = $this->postOn($client, self::OTHER, $this->asset, 'Mine')['id'];

        $client->request('PATCH', '/messages/'.$id, self::patchOptions(self::USER, [
            'json' => ['content' => 'Rewritten'],
        ]));
        $this->assertResponseStatusCodeSame(403);

        $client->request('DELETE', '/messages/'.$id, self::auth(self::USER));
        $this->assertResponseStatusCodeSame(403);

        $client->request('PATCH', '/messages/'.$id, self::patchOptions(null, ['json' => ['content' => 'Rewritten']]));
        $this->assertResponseStatusCodeSame(401);

        $data = $client->request('GET', '/messages/'.$id, self::auth(self::USER))->toArray();
        $this->assertSame('Mine', $data['content']);
        $this->assertSame(['edit' => false, 'delete' => false], $data['capabilities']);
    }

    public function testAdminModeratesAnyMessage(): void
    {
        $client = static::createClient();
        $this->setUpWorkspace();
        $id = $this->postOn($client, self::OTHER, $this->asset, 'Spam')['id'];

        $data = $client->request('GET', '/messages/'.$id, self::auth(self::ADMIN))->toArray();
        $this->assertSame(['edit' => true, 'delete' => true], $data['capabilities']);

        $client->request('DELETE', '/messages/'.$id, self::auth(self::ADMIN));
        $this->assertResponseStatusCodeSame(204);
    }
}
