<?php

declare(strict_types=1);

namespace App\Tests\Functional\Asset;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use App\Entity\Asset;
use App\Entity\Commit;
use App\Storage\AssetManager;
use App\Tests\Functional\AbstractUploaderTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AssetDeleteTest extends AbstractUploaderTestCase
{
    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $path = sprintf('test/%s.jpg', uniqid());
        self::getService(FileStorageManager::class)->store($path, 'content');

        $this->asset = self::getService(AssetManager::class)->createAsset(
            $this->getOrCreateDefaultTarget(),
            $path,
            'image/jpeg',
            'foo.jpg',
            7,
            KeycloakClientTestMock::USER_UID,
        );
    }

    public function testOwnerDeletesUncommittedAsset(): void
    {
        $response = $this->requestDelete(KeycloakClientTestMock::USER_UID);

        $this->assertSame(204, $response->getStatusCode());
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(Asset::class, $this->asset->getId()));
        $this->assertFalse(self::getService(FileStorageManager::class)->has($this->asset->getPath()));
    }

    public function testOtherUserCannotDeleteAsset(): void
    {
        $response = $this->requestDelete(KeycloakClientTestMock::OTHER_USER_UID);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertTrue(self::getService(FileStorageManager::class)->has($this->asset->getPath()));
    }

    public function testCommittedAssetCannotBeDeleted(): void
    {
        $commit = new Commit();
        $commit->setTarget($this->getOrCreateDefaultTarget());
        $commit->setToken('secret_token');
        $commit->setUserId(KeycloakClientTestMock::USER_UID);
        $commit->setFormData([]);
        $commit->setTotalSize(7);
        $em = self::getEntityManager();
        $em->persist($commit);
        $em->flush();
        $em->getRepository(Asset::class)->attachCommit([$this->asset->getId()], $commit->getId());
        $em->refresh($this->asset);

        $response = $this->requestDelete(KeycloakClientTestMock::USER_UID);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousCannotDeleteAsset(): void
    {
        $response = $this->requestDelete(null);

        $this->assertSame(401, $response->getStatusCode());
    }

    private function requestDelete(?string $userId): ResponseInterface
    {
        return static::createClient()->request('DELETE', sprintf('/assets/%s', $this->asset->getId()), [
            'headers' => $userId ? [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ] : [],
        ]);
    }
}
