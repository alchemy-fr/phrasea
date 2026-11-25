<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use ApiPlatform\Test\Client;
use App\Consumer\Handler\AssetConsumerNotify;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Entity\Target;
use App\Storage\AssetManager;
use App\Storage\CommitManager;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PostCommitTest extends AbstractUploaderTestCase
{
    public function testCommit(): void
    {
        $target = $this->createTarget();
        $asset1 = $this->createAsset($target);
        $asset2 = $this->createAsset($target);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/commit', [
            'files' => [$asset1->getId(), $asset2->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertTrue(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $em = self::getEntityManager();
        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $commit = $commits[0];
        $this->assertEquals(KeycloakClientTestMock::ADMIN_UID, $commit->getUserId());
        $this->assertEquals(84, (int) $commit->getTotalSize());
        $this->assertCount(2, $commit->getAssets());
    }

    public function testRetryingACommitAlreadyStoredSucceedsWithoutASecondCommit(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];

        for ($i = 0; $i < 2; ++$i) {
            $response = $this->commit(KeycloakClientTestMock::ADMIN_UID, $target, $files);
            $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
            $this->assertTrue(json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
            // The files in another order are the same commit
            $files = array_reverse($files);
        }

        $em = self::getEntityManager();
        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $this->assertCount(2, $commits[0]->getAssets());
    }

    public function testRetryingACommitDoesNotNotifyTheTargetAgain(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];

        $response = $this->commitIntercepted($target, $files, $notified);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(1, $notified);

        $response = $this->commitIntercepted($target, $files, $notified);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(0, $notified);
    }

    public function testRetryingACommitWhoseNotificationFailedNotifiesTheTarget(): void
    {
        $target = $this->createTarget();
        // On the private network: the notification fails (sync transport) without any request
        $target->setTargetUrl('http://10.0.0.1/enqueue');
        $target->setAuthorizationKey('secret_token');
        self::getEntityManager()->flush();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];

        $response = $this->commit(KeycloakClientTestMock::ADMIN_UID, $target, $files);
        $this->assertEquals(500, $response->getStatusCode(), $response->getContent(false));
        $em = self::getEntityManager();
        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $this->assertNull($commits[0]->getNotifyDispatchedAt());

        $response = $this->commitIntercepted($target, $files, $notified);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(1, $notified);
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNotNull($em->find(Commit::class, $commits[0]->getId())->getNotifyDispatchedAt());

        $response = $this->commitIntercepted($target, $files, $notified);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(0, $notified);
    }

    public function testRetryingAnAcknowledgedCommitDoesNotNotifyTheTarget(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId()];
        $commit = $this->newCommit($target, $files);
        $commit->setAcknowledged(true);
        $this->assertTrue(self::getService(CommitManager::class)->store($commit));

        $response = $this->commitIntercepted($target, $files, $notified);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertSame(0, $notified);
    }

    public function testRetryingWithOtherFilesThanTheStoredCommitReturns403(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];
        $notCommitted = $this->createAsset($target)->getId();
        $response = $this->commit(KeycloakClientTestMock::ADMIN_UID, $target, $files);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));

        $response = $this->commit(KeycloakClientTestMock::ADMIN_UID, $target, [$files[0]]);
        $this->assertEquals(403, $response->getStatusCode(), 'Subset');

        $response = $this->commit(KeycloakClientTestMock::ADMIN_UID, $target, [...$files, $notCommitted]);
        $this->assertEquals(403, $response->getStatusCode(), 'Superset');

        $em = self::getEntityManager();
        $em->clear();
        $this->assertCount(1, $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]));
    }

    public function testRetryingTheCommitOfAnotherUserReturns403(): void
    {
        $target = $this->createTarget();
        $asset = $this->createAsset($target, KeycloakClientTestMock::OTHER_USER_UID);
        $response = $this->commit(KeycloakClientTestMock::OTHER_USER_UID, $target, [$asset->getId()]);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));

        $response = $this->commit(KeycloakClientTestMock::USER_UID, $target, [$asset->getId()]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testARetryRacingTheStorageOfTheSameCommitSucceeds(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];

        $client = static::createClient();
        // Built aside: the service itself must stay uninitialized to be replaced
        $realManager = new CommitManager(self::getEntityManager(), self::getService(AssetManager::class));
        $manager = $this->createMock(CommitManager::class);
        // The first request stores the commit once the second one passed the checks
        $manager->expects($this->once())->method('store')->willReturnCallback(function () use ($realManager, $target, $files): bool {
            $this->assertTrue($realManager->store($this->newCommit($target, $files)));

            return false;
        });

        $response = $this->commitWith($client, $manager, KeycloakClientTestMock::ADMIN_UID, $target, $files);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
    }

    public function testARacingCommitOfOtherFilesIsAConflict(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];

        $client = static::createClient();
        // Built aside: the service itself must stay uninitialized to be replaced
        $realManager = new CommitManager(self::getEntityManager(), self::getService(AssetManager::class));
        $manager = $this->createMock(CommitManager::class);
        $manager->expects($this->once())->method('store')->willReturnCallback(function () use ($realManager, $target, $files): bool {
            $this->assertTrue($realManager->store($this->newCommit($target, [$files[0]])));

            return false;
        });

        $response = $this->commitWith($client, $manager, KeycloakClientTestMock::ADMIN_UID, $target, $files);
        $this->assertEquals(409, $response->getStatusCode(), $response->getContent(false));
    }

    public function testFilesCommittedTwiceAtOnceGoWithOneCommitOnly(): void
    {
        $target = $this->createTarget();
        $files = [$this->createAsset($target)->getId(), $this->createAsset($target)->getId()];
        $manager = self::getService(CommitManager::class);

        $first = $this->newCommit($target, $files);
        $this->assertTrue($manager->store($first));

        // Both passed the checks before either was stored
        $this->assertFalse($manager->store($this->newCommit($target, $files)));
        $this->assertFalse($manager->store($this->newCommit($target, [$files[0], $this->createAsset($target)->getId()])), 'One file only taken');

        $em = self::getEntityManager();
        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $this->assertSame($first->getId(), $commits[0]->getId());
        $this->assertCount(2, $commits[0]->getAssets());
    }

    public function testACommitWhoseFilesWereTakenMeanwhileIsAConflict(): void
    {
        $target = $this->createTarget();
        $asset = $this->createAsset($target);

        $client = static::createClient();
        $manager = $this->createMock(CommitManager::class);
        $manager->expects($this->once())->method('store')->willReturn(false);
        static::getContainer()->set(CommitManager::class, $manager);

        $response = $client->request('POST', '/commit', [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID)],
            'json' => [
                'files' => [$asset->getId()],
                'formData' => [],
                'target' => '/targets/'.$target->getId(),
            ],
        ]);
        $this->assertEquals(409, $response->getStatusCode(), $response->getContent(false));
    }

    /**
     * @param string[] $files
     */
    private function newCommit(Target $target, array $files): Commit
    {
        $commit = new Commit();
        $commit->setTarget($target);
        $commit->setFiles($files);
        $commit->setUserId(KeycloakClientTestMock::ADMIN_UID);

        return $commit;
    }

    public function testCommitWithUnknownTargetSlugReturns400(): void
    {
        $target = $this->createTarget();
        $asset = $this->createAsset($target);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), 'POST', '/commit', [
            'files' => [$asset->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
            'targetSlug' => 'unknown',
        ]);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCommitToATargetOutsideTheUserGroupsReturns403(): void
    {
        $target = $this->createTarget();
        $target->setAllowedGroups(['restricted-group']);
        $em = self::getEntityManager();
        $em->flush();
        $asset = $this->createAsset($target, KeycloakClientTestMock::USER_UID);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/commit', [
            'files' => [$asset->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCommitOfAnotherUserFilesReturns403(): void
    {
        $target = $this->createTarget();
        $asset = $this->createAsset($target, KeycloakClientTestMock::OTHER_USER_UID);

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/commit', [
            'files' => [$asset->getId()],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCommitWithAnonymousUserReturns401(): void
    {
        $target = $this->createTarget();

        $response = $this->request(null, 'POST', '/commit', [
            'files' => [],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * @param string[] $files
     */
    private function commit(string $userId, Target $target, array $files): ResponseInterface
    {
        return $this->request(KeycloakClientTestMock::getJwtFor($userId), 'POST', '/commit', [
            'files' => $files,
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
    }

    /**
     * Commits as the admin, the notifications of the target queued instead of handled.
     *
     * @param string[] $files
     *
     * @param-out int $notified the notifications queued by the request
     */
    private function commitIntercepted(Target $target, array $files, ?int &$notified): ResponseInterface
    {
        $client = static::createClient();
        /** @var TestTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.p2');
        $queue = $transport->intercept();

        $response = $client->request('POST', '/commit', [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID)],
            'json' => [
                'files' => $files,
                'formData' => [],
                'target' => '/targets/'.$target->getId(),
            ],
        ]);
        $response->getStatusCode();

        $notified = count(array_filter(
            iterator_to_array($queue->getSent()),
            static fn ($envelope): bool => $envelope->getMessage() instanceof AssetConsumerNotify,
        ));

        return $response;
    }

    /**
     * @param string[] $files
     */
    private function commitWith(Client $client, CommitManager $manager, string $userId, Target $target, array $files): ResponseInterface
    {
        static::getContainer()->set(CommitManager::class, $manager);

        return $client->request('POST', '/commit', [
            'headers' => ['Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId)],
            'json' => [
                'files' => $files,
                'formData' => [],
                'target' => '/targets/'.$target->getId(),
            ],
        ]);
    }

    private function createTarget(): Target
    {
        $em = self::getEntityManager();

        $target = new Target();
        $target->setName('Commit target');
        $target->setSlug('commit-target');
        // No target URL: the commit is not pushed to a consumer
        $target->setTargetUrl(null);
        $em->persist($target);
        $em->flush();

        return $target;
    }

    private function createAsset(Target $target, string $userId = KeycloakClientTestMock::ADMIN_UID): Asset
    {
        $asset = new Asset();
        $asset->setTarget($target);
        $asset->setMimeType('image/jpeg');
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId($userId);

        $em = self::getEntityManager();
        $em->persist($asset);
        $em->flush();

        return $asset;
    }
}
