<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;
use App\Entity\Asset;
use App\Entity\Commit;

class CommitAckTest extends AbstractUploaderTestCase
{
    public function testCommitAck(): void
    {
        $commit = $this->createCommit();
        $asset1 = $this->createAsset($commit);
        $asset2 = $this->createAsset($commit);
        $em = $this->getEntityManager();
        $em->flush();

        $this->assertAssetAcknowledgement($asset1->getId(), false);
        $this->assertAssetAcknowledgement($asset2->getId(), false);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'POST',
            '/commits/'.$commit->getId().'/ack'
        );
        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertAssetAcknowledgement($asset1->getId(), true);
        $this->assertAssetAcknowledgement($asset2->getId(), true);
    }

    public function testAssetAck(): void
    {
        $commit = $this->createCommit();
        $asset1 = $this->createAsset($commit);
        $asset2 = $this->createAsset($commit);
        $em = $this->getEntityManager();
        $em->flush();

        $this->assertAssetAcknowledgement($asset1->getId(), false);
        $this->assertAssetAcknowledgement($asset2->getId(), false);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'POST',
            '/assets/'.$asset1->getId().'/ack'
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCommitAcknowledgement($commit->getId(), false);
        $this->assertAssetAcknowledgement($asset1->getId(), true);
        $this->assertAssetAcknowledgement($asset2->getId(), false);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'POST',
            '/assets/'.$asset2->getId().'/ack'
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(true, $json);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCommitAcknowledgement($commit->getId(), true);
        $this->assertAssetAcknowledgement($asset1->getId(), true);
        $this->assertAssetAcknowledgement($asset2->getId(), true);
    }

    private function assertAssetAcknowledgement(string $id, bool $acknowledged): void
    {
        $em = $this->getEntityManager();
        $em->clear();

        $asset = $em->find(Asset::class, $id);
        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertEquals($acknowledged, $asset->isAcknowledged());
    }

    private function assertCommitAcknowledgement(string $id, bool $acknowledged): void
    {
        $em = $this->getEntityManager();
        $em->clear();

        $commit = $em->find(Commit::class, $id);
        $this->assertInstanceOf(Commit::class, $commit);
        $this->assertEquals($acknowledged, $commit->isAcknowledged());
    }

    private function createCommit(): Commit
    {
        $commit = new Commit();
        $commit->setTarget($this->getOrCreateDefaultTarget());
        $commit->setTotalSize(42);
        $commit->setUserId('user_id');
        $commit->setToken('secret_token');

        $em = $this->getEntityManager();
        $em->persist($commit);

        return $commit;
    }

    private function createAsset(Commit $commit): Asset
    {
        $asset = new Asset();
        $asset->setTarget($this->getOrCreateDefaultTarget());
        $asset->setMimeType('image/jpeg');
        $asset->setCommit($commit);
        $asset->setPath('a/b/c.jpeg');
        $asset->setSize(42);
        $asset->setOriginalName('foo.jpeg');
        $asset->setUserId('user_id');

        $commit->getAssets()->add($asset);

        $em = $this->getEntityManager();
        $em->persist($asset);

        return $asset;
    }
}
