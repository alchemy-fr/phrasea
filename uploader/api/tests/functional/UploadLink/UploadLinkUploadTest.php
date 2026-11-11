<?php

declare(strict_types=1);

namespace App\Tests\Functional\UploadLink;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Exception\UploadLinkQuotaExceededException;
use App\Repository\UploadLinkRepository;
use App\Tests\Functional\TargetFormTest;
use Symfony\Component\HttpClient\HttpClient;

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
        $this->assertSame(['title' => 'My pictures'], $reloaded->getLastFormData(), 'What was entered only');
        $this->assertNotNull($reloaded->getLastCommitAt());
        $this->assertSame(1, $reloaded->getUsedAssets(), 'A committed file stays counted');

        $response = $this->linkRequest($link->getToken(), 'GET', '/upload-links/current');
        $this->assertSame(['title' => 'My pictures'], $response->toArray()['lastFormData']);
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

        $headers = HttpClient::create()->request('PUT', $upload['urls'][1], ['body' => $content])->getHeaders();
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

        // Over the total size: refused before completing it
        $response = $this->linkRequest($link->getToken(), 'POST', '/uploads', [
            'filename' => 'file.jpg',
            'type' => 'image/jpeg',
            'size' => 1,
        ]);
        $upload = $response->toArray();
        $headers = HttpClient::create()->request('PUT', $upload['urls'][1], ['body' => 'x'])->getHeaders();
        $response = $this->linkRequest($link->getToken(), 'POST', '/assets', [
            'multipart' => [
                'uploadId' => $upload['id'],
                'parts' => [['ETag' => $headers['etag'][0], 'PartNumber' => 1]],
            ],
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(1, $this->reloadLink($link)->getUsedAssets());
    }
}
