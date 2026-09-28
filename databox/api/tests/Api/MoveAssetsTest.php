<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\Workspace;
use App\Tests\AbstractSearchTestCase;

/**
 * POST /assets/move: an asset is moved within its workspace only (its
 * reference collection changes, the workspace never does).
 */
class MoveAssetsTest extends AbstractSearchTestCase
{
    public function testMoveAssetsToACollectionOfTheirWorkspace(): void
    {
        self::enableFixtures();

        $collectionId = $this->getCollectionId('Collection #1');
        $assetIds = [$this->getAssetId('foo'), $this->getAssetId('bar')];

        $this->move('/collections/'.$collectionId, $assetIds);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getEntityManager();
        $em->clear();
        foreach ($assetIds as $assetId) {
            $asset = $em->find(Asset::class, $assetId);
            $this->assertSame($collectionId, $asset->getReferenceCollection()?->getId());
        }
    }

    public function testMoveAssetsToACollectionOfAnotherWorkspaceIsRejected(): void
    {
        self::enableFixtures();

        $otherCollection = $this->createOtherWorkspaceCollection();
        $assetId = $this->getAssetId('foo');

        $response = $this->move('/collections/'.$otherCollection->getId(), [$assetId]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('cannot be moved to another workspace', $response->getContent(false));

        $em = self::getEntityManager();
        $em->clear();
        $this->assertCount(0, $em->getRepository(CollectionAsset::class)->findBy([
            'collection' => $otherCollection->getId(),
        ]));
    }

    public function testMoveAssetsToAnotherWorkspaceIsRejected(): void
    {
        self::enableFixtures();

        $otherWorkspace = $this->createOtherWorkspaceCollection()->getWorkspace();

        $this->move('/workspaces/'.$otherWorkspace->getId(), [$this->getAssetId('foo')]);
        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @param string[] $assetIds
     */
    private function move(string $destination, array $assetIds): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return static::createClient()->request('POST', '/assets/move', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'destination' => $destination,
                'ids' => $assetIds,
            ],
        ]);
    }

    private function createOtherWorkspaceCollection(): Collection
    {
        $em = self::getEntityManager();

        $workspace = new Workspace();
        $workspace->setName('Other workspace');
        $workspace->setSlug('other-workspace');
        $workspace->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $em->persist($workspace);

        $collection = new Collection();
        $collection->setName('Other collection');
        $collection->setOwnerId(KeycloakClientTestMock::ADMIN_UID);
        $collection->setWorkspace($workspace);
        $em->persist($collection);
        $em->flush();

        return $collection;
    }

    private function getAssetId(string $key): string
    {
        return self::getEntityManager()
            ->getRepository(Asset::class)
            ->findOneBy(['key' => $key])
            ->getId();
    }

    private function getCollectionId(string $name): string
    {
        return self::getEntityManager()
            ->getRepository(Collection::class)
            ->findOneBy(['name' => $name])
            ->getId();
    }
}
