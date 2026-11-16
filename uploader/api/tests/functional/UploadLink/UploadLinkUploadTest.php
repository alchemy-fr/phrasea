<?php

declare(strict_types=1);

namespace App\Tests\Functional\UploadLink;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Controller\Admin\AssetCrudController;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Exception\UploadLinkQuotaExceededException;
use App\Repository\UploadLinkRepository;
use App\Storage\AssetManager;
use App\Tests\Functional\TargetFormTest;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

class UploadLinkUploadTest extends AbstractUploadLinkTestCase
{
    public function testUploadAndCommitThroughALink(): void
    {
        $target = $this->createTarget();
        $target->setForm(TargetFormTest::definition());
        $target->setData(['fixed' => 'from the target']);
        $link = $this->createLink($target, KeycloakClientTestMock::OTHER_USER_UID, ['maxAssets' => 5]);

        $response = $this->uploadFile($link->getToken());
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $assetId = $response->toArray()['id'];

        $em = self::getEntityManager();
        $em->clear();
        $asset = $em->find(Asset::class, $assetId);
        $this->assertSame(KeycloakClientTestMock::OTHER_USER_UID, $asset->getUserId(), 'Attributed to the creator of the link');
        $this->assertSame($link->getId(), $asset->getUploadLink()?->getId());
        $this->assertSame($target->getId(), $asset->getTarget()->getId());

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());

        $response = $this->linkRequest($link->getToken(), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [
                'title' => 'My pictures',
                '__notify' => true,
            ],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));

        $em->clear();
        $commits = $em->getRepository(Commit::class)->findBy(['target' => $target->getId()]);
        $this->assertCount(1, $commits);
        $commit = $commits[0];
        $this->assertSame(KeycloakClientTestMock::OTHER_USER_UID, $commit->getUserId());
        $this->assertSame($link->getId(), $commit->getUploadLink()?->getId());
        $this->assertFalse($commit->isNotify(), 'Nobody to notify behind a link');
        $this->assertSame('from the target', $commit->getFormData()['fixed']);
        $this->assertCount(1, $commit->getAssets());

        $reloaded = $this->reloadLink($link);
        $this->assertNotNull($reloaded->getLastCommitAt());
        $this->assertSame(1, $reloaded->getUsedAssets(), 'A committed file stays counted');

        // Kept by the browser that entered it: the other holders of the link must not see it
        $response = $this->linkRequest($link->getToken(), 'GET', '/upload-links/current');
        $this->assertArrayNotHasKey('lastFormData', $response->toArray());
    }

    public function testTheDataOfTheFileIsIgnored(): void
    {
        $link = $this->createLink($this->createTarget());

        $response = $this->uploadFile($link->getToken(), [
            'data' => [
                'targetAsset' => 'b0a2b1e0-6bd5-4f6a-9a1f-2a3c4d5e6f70',
                'targetRendition' => 'c1b3c2f1-7ce6-4a7b-8b2a-3b4d5e6f7a81',
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));

        $em = self::getEntityManager();
        $em->clear();
        $asset = $em->find(Asset::class, $response->toArray()['id']);
        $this->assertSame([], $asset->getData());
        $this->assertTrue($asset->isFromUploadLink());
    }

    public function testTheTargetOfTheLinkIsImposed(): void
    {
        $target = $this->createTarget();
        $other = $this->createTarget('other-target');
        $link = $this->createLink($target);

        $response = $this->uploadFile($link->getToken(), ['targetId' => $other->getId()]);
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->uploadFile($link->getToken(), ['targetSlug' => $target->getSlug()]);
        $this->assertSame(201, $response->getStatusCode());
        $assetId = $response->toArray()['id'];

        $response = $this->linkRequest($link->getToken(), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [],
            'target' => '/targets/'.$other->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testADisabledLinkCannotUpload(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['enabled' => false]);

        $response = $this->uploadFile($link->getToken());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('upload_link_disabled', $response->toArray(false)['code']);
    }

    public function testTheNumberOfFilesIsLimited(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 1]);

        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $response = $this->uploadFile($link->getToken());
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringEndsWith(UploadLinkQuotaExceededException::CODE, $response->toArray(false)['type']);

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
        $this->assertCount(1, self::getEntityManager()->getRepository(Asset::class)->findBy(['uploadLink' => $link->getId()]));
    }

    /**
     * The link is read from the database by the request, as in production: its counters, updated in SQL,
     * must not be written back with their former value when the asset is flushed.
     */
    public function testTheCountersSurviveTheFlushOfTheAsset(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 10, 'maxTotalSize' => 10 * self::FIXTURE_SIZE]);
        self::getEntityManager()->clear();

        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());
        self::getEntityManager()->clear();
        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $reloaded = $this->reloadLink($link);
        $this->assertSame(2, $reloaded->getUsedAssets());
        $this->assertSame(2 * self::FIXTURE_SIZE, $reloaded->getUsedSize());
        $this->assertSame(10 * self::FIXTURE_SIZE, $reloaded->getMaxTotalSize());
    }

    public function testALongFileNameIsShortenedBeforeTheReservation(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 1]);

        $response = $this->uploadFileNamed($link->getToken(), str_repeat('a', 300).'.jpg');
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));

        $em = self::getEntityManager();
        $em->clear();
        $asset = $em->find(Asset::class, $response->toArray()['id']);
        $this->assertSame(str_repeat('a', 251).'.jpg', $asset->getOriginalName());
        $this->assertSame(1, $this->reloadLink($link)->getUsedAssets());
    }

    public function testAnAssetThatCannotBeCreatedGivesBackItsReservationAndItsFile(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 1]);

        $client = static::createClient();
        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->once())->method('createAsset')->willThrowException(new \RuntimeException('The asset cannot be inserted'));
        static::getContainer()->set(AssetManager::class, $assetManager);
        $stored = null;
        $storage = $this->createMock(FileStorageManager::class);
        $storage->expects($this->once())->method('storeStream')->willReturnCallback(function (string $path) use (&$stored): void {
            $stored = $path;
        });
        $storage->expects($this->once())->method('delete')->willReturnCallback(function (string $path) use (&$stored): void {
            $this->assertSame($stored, $path, 'The stored file is removed');
        });
        static::getContainer()->set(FileStorageManager::class, $storage);

        $response = $client->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'UploadLink '.$link->getToken(),
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(self::FIXTURE, '32x32.jpg', 'image/jpeg'),
                ],
            ],
        ]);
        $this->assertSame(500, $response->getStatusCode());

        $reloaded = $this->reloadLink($link);
        $this->assertSame(0, $reloaded->getUsedAssets());
        $this->assertSame(0, $reloaded->getUsedSize());
    }

    private function uploadFileNamed(string $token, string $name): ResponseInterface
    {
        return static::createClient()->request('POST', '/assets', [
            'headers' => [
                'Authorization' => 'UploadLink '.$token,
                'Content-Type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'file' => new UploadedFile(self::FIXTURE, $name, 'image/jpeg'),
                ],
            ],
        ]);
    }

    public function testTheTotalSizeIsLimited(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxTotalSize' => self::FIXTURE_SIZE + 100]);

        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $response = $this->uploadFile($link->getToken());
        $this->assertSame(400, $response->getStatusCode());

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
    }

    public function testTheReservationIsAtomic(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 2, 'maxTotalSize' => 100]);
        $repository = self::getService(UploadLinkRepository::class);
        $now = new \DateTimeImmutable();

        $this->assertTrue($repository->reserve($link->getId(), 60, $now));
        $this->assertFalse($repository->reserve($link->getId(), 60, $now), 'Over the total size');
        $this->assertTrue($repository->reserve($link->getId(), 40, $now));
        $this->assertFalse($repository->reserve($link->getId(), 1, $now), 'Over the number of files');

        $repository->release($link->getId(), 70);
        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(30, $reloaded->getUsedSize());

        $repository->release($link->getId(), 70);
        $repository->release($link->getId(), 70);
        $reloaded = $this->reloadLink($link);
        $this->assertSame(0, $reloaded->getUsedAssets(), 'Never below zero');
        $this->assertSame(0, $reloaded->getUsedSize());

        $expired = $this->createLink($this->createTarget('expired-target'), options: ['expiresAt' => new \DateTimeImmutable('-1 minute')]);
        $this->assertFalse($repository->reserve($expired->getId(), 1, $now));
    }

    public function testRemovingAFileNotCommittedReleasesTheQuota(): void
    {
        $target = $this->createTarget();
        $link = $this->createLink($target, options: ['maxAssets' => 1]);
        $otherLink = $this->createLink($target);

        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];

        $response = $this->linkRequest($otherLink->getToken(), 'DELETE', '/assets/'.$assetId);
        $this->assertSame(403, $response->getStatusCode(), 'Another link');
        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'DELETE', '/assets/'.$assetId);
        $this->assertSame(403, $response->getStatusCode(), 'The creator of the link');

        $response = $this->linkRequest($link->getToken(), 'DELETE', '/assets/'.$assetId);
        $this->assertSame(204, $response->getStatusCode());

        $reloaded = $this->reloadLink($link);
        $this->assertSame(0, $reloaded->getUsedAssets());
        $this->assertSame(0, $reloaded->getUsedSize());

        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode(), 'The quota was given back');

        $response = $this->linkRequest($link->getToken(), 'DELETE', '/assets/'.$assetId);
        $this->assertSame(404, $response->getStatusCode(), 'Already deleted');
        $this->assertSame(1, $this->reloadLink($link)->getUsedAssets());
    }

    public function testTheQuotaIsReleasedOnceWhenTheSameFileIsDeletedTwice(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 2]);
        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];
        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        // Two instances of the same file, as read by two concurrent requests
        $em = self::getEntityManager();
        $em->clear();
        $first = $em->find(Asset::class, $assetId);
        $em->clear();
        $second = $em->find(Asset::class, $assetId);
        $this->assertNotSame($first, $second);

        $assetManager = self::getService(AssetManager::class);
        $this->assertTrue($assetManager->removeUncommitted($first));
        $this->assertFalse($assetManager->removeUncommitted($second), 'Already deleted');

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
        $this->assertNull($em->find(Asset::class, $assetId));
    }

    public function testRemovingADeletedFileThroughTheOrmReleasesNothing(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 2]);
        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];

        $em = self::getEntityManager();
        $em->clear();
        $stale = $em->find(Asset::class, $assetId);
        $em->getConnection()->executeStatement('DELETE FROM asset WHERE id = :id', ['id' => $assetId]);

        $em->remove($stale);
        $em->flush();

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets(), 'Released by the deletion that actually happened only');
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
    }

    public function testDeletingAFileCommittedMeanwhileReleasesNothing(): void
    {
        $target = $this->createTarget();
        $link = $this->createLink($target, options: ['maxAssets' => 2]);
        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];

        $em = self::getEntityManager();
        $em->clear();
        $stale = $em->find(Asset::class, $assetId);
        $this->assertFalse($stale->isCommitted());

        $response = $this->linkRequest($link->getToken(), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent(false));

        $this->assertFalse(self::getService(AssetManager::class)->removeUncommitted($stale));

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
        $asset = self::getEntityManager()->find(Asset::class, $assetId);
        $this->assertNotNull($asset);
        $this->assertTrue($asset->isCommitted());
    }

    public function testTheAdminDeletesAFileNotCommittedOnce(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 2]);
        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];
        $this->assertSame(201, $this->uploadFile($link->getToken())->getStatusCode());

        $em = self::getEntityManager();
        $em->clear();
        $first = $em->find(Asset::class, $assetId);
        $em->clear();
        $second = $em->find(Asset::class, $assetId);

        $controller = self::getService(AssetCrudController::class);
        $controller->deleteEntity($em, $first);
        $controller->deleteEntity($em, $second);

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
        $this->assertNull($em->find(Asset::class, $assetId));
    }

    public function testCleaningAnExpiredFileNotCommittedReleasesTheQuota(): void
    {
        $link = $this->createLink($this->createTarget(), options: ['maxAssets' => 2]);
        $assetId = $this->uploadFile($link->getToken())->toArray()['id'];
        $keptId = $this->uploadFile($link->getToken())->toArray()['id'];

        self::getEntityManager()->getConnection()->executeStatement(
            'UPDATE asset SET created_at = :createdAt WHERE id = :id',
            ['createdAt' => new \DateTimeImmutable('-10 days'), 'id' => $assetId],
            ['createdAt' => Types::DATETIME_IMMUTABLE],
        );
        self::getEntityManager()->clear();

        self::getService(AssetManager::class)->cleanAssets(5);

        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Asset::class, $assetId));
        $this->assertNotNull($em->find(Asset::class, $keptId));

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(self::FIXTURE_SIZE, $reloaded->getUsedSize());
    }

    public function testTheFilesOfAnotherLinkCannotBeCommitted(): void
    {
        $target = $this->createTarget();
        $link = $this->createLink($target);
        $otherLink = $this->createLink($target);

        $assetId = $this->uploadFile($otherLink->getToken())->toArray()['id'];

        $response = $this->linkRequest($link->getToken(), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode());

        $response = $this->request(KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ]);
        $this->assertSame(403, $response->getStatusCode(), 'Not even by the creator of the link');
    }

    public function testMultipartUploadThroughALink(): void
    {
        $content = 'Hello, multipart!';
        $link = $this->createLink($this->createTarget(), options: ['maxTotalSize' => strlen($content)]);

        $response = $this->linkRequest($link->getToken(), 'POST', '/uploads', [
            'filename' => 'file.jpg',
            'type' => 'image/jpeg',
            'size' => strlen($content),
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $upload = $response->toArray();

        $this->assertSame('upload-link:'.$link->getId(), self::getEntityManager()->find(MultipartUpload::class, $upload['id'])->getOwnerId());

        $headers = self::getStorageClient()->request('PUT', $upload['urls'][1], ['body' => $content])->getHeaders();
        $response = $this->linkRequest($link->getToken(), 'POST', '/assets', [
            'multipart' => [
                'uploadId' => $upload['id'],
                'parts' => [['ETag' => $headers['etag'][0], 'PartNumber' => 1]],
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));

        $reloaded = $this->reloadLink($link);
        $this->assertSame(1, $reloaded->getUsedAssets());
        $this->assertSame(strlen($content), $reloaded->getUsedSize());

        // Over the total size: refused before anything is opened on the storage
        $response = $this->linkRequest($link->getToken(), 'POST', '/uploads', [
            'filename' => 'file.jpg',
            'type' => 'image/jpeg',
            'size' => 1,
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringEndsWith(UploadLinkQuotaExceededException::CODE, $response->toArray(false)['type']);
        $this->assertSame(1, $this->reloadLink($link)->getUsedAssets());
    }

    public function testAMultipartUploadStartedWithinTheQuotaIsRefusedOnCompletionOnceOver(): void
    {
        $content = 'Hello, multipart!';
        $link = $this->createLink($this->createTarget(), options: ['maxTotalSize' => strlen($content)]);

        $uploads = [];
        foreach ([$content, 'x'] as $body) {
            $response = $this->linkRequest($link->getToken(), 'POST', '/uploads', [
                'filename' => 'file.jpg',
                'type' => 'image/jpeg',
                'size' => strlen($body),
            ]);
            $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
            $upload = $response->toArray();
            $headers = self::getStorageClient()->request('PUT', $upload['urls'][1], ['body' => $body])->getHeaders();
            $uploads[] = [
                'uploadId' => $upload['id'],
                'parts' => [['ETag' => $headers['etag'][0], 'PartNumber' => 1]],
            ];
        }

        $response = $this->linkRequest($link->getToken(), 'POST', '/assets', ['multipart' => $uploads[0]]);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));

        // Both fitted when they were started, not together: the reservation on completion decides
        $response = $this->linkRequest($link->getToken(), 'POST', '/assets', ['multipart' => $uploads[1]]);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(1, $this->reloadLink($link)->getUsedAssets());
    }

    public function testTheMultipartUploadsOfAHolderBelongToTheirBrowserSession(): void
    {
        $content = 'Hello, multipart!';
        $target = $this->createTarget();
        $link = $this->createLink($target);
        $sessionA = Uuid::v4()->toRfc4122();
        $sessionB = Uuid::v4()->toRfc4122();

        $response = $this->linkRequest($link->getToken(), 'POST', '/uploads', [
            'filename' => 'file.jpg',
            'type' => 'image/jpeg',
            'size' => strlen($content),
        ], $sessionA);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $upload = $response->toArray();
        $this->assertSame('upload-link:'.$link->getId().':'.$sessionA, self::getEntityManager()->find(MultipartUpload::class, $upload['id'])->getOwnerId());

        $headers = self::getStorageClient()->request('PUT', $upload['urls'][1], ['body' => $content])->getHeaders();
        $complete = [
            'multipart' => [
                'uploadId' => $upload['id'],
                'parts' => [['ETag' => $headers['etag'][0], 'PartNumber' => 1]],
            ],
        ];

        foreach ([$sessionB, null] as $other) {
            $response = $this->linkRequest($link->getToken(), 'GET', '/uploads/'.$upload['id'], null, $other);
            $this->assertSame(403, $response->getStatusCode(), 'Read by another holder of the link');
            $response = $this->linkRequest($link->getToken(), 'DELETE', '/uploads/'.$upload['id'], null, $other);
            $this->assertSame(403, $response->getStatusCode(), 'Cancelled by another holder of the link');
            $response = $this->linkRequest($link->getToken(), 'POST', '/assets', $complete, $other);
            $this->assertSame(403, $response->getStatusCode(), 'Completed by another holder of the link');
        }

        $response = $this->linkRequest($link->getToken(), 'POST', '/assets', $complete, strtoupper($sessionA));
        $this->assertSame(201, $response->getStatusCode(), $response->getContent(false));
        $assetId = $response->toArray()['id'];

        $response = $this->linkRequest($link->getToken(), 'POST', '/commit', [
            'files' => [$assetId],
            'formData' => [],
            'target' => '/targets/'.$target->getId(),
        ], $sessionB);
        $this->assertSame(200, $response->getStatusCode(), 'The files of the link are committed whoever sent them');
    }

    public function testAnInvalidBrowserSessionIsRefused(): void
    {
        $link = $this->createLink($this->createTarget());

        $response = $this->linkRequest($link->getToken(), 'GET', '/upload-links/current', null, 'not-a-uuid');

        $this->assertSame(400, $response->getStatusCode());
    }
}
