<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class ExposeSynchronizerTest extends AbstractDataboxTestCase
{
    use ExposeTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;

    private FakeExposeApi $api;
    private Workspace $workspace;
    private WorkspaceIntegration $integration;
    private string $publicationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new FakeExposeApi();
        $this->workspace = $this->createWorkspace(['ownerId' => self::OWNER]);
        $this->integration = $this->createExposeIntegration(null, self::OWNER);
        $this->createExposeToken($this->integration, self::OWNER);
        $this->publicationId = $this->api->addPublication('Basket publication');
    }

    private function createSynchronizer(): ExposeSynchronizer
    {
        $client = self::createExposeClient($this->api);
        $synchronizer = new ExposeSynchronizer(
            $client,
            self::createPublisher($client),
            self::getService(IntegrationManager::class),
            self::getService(IntegrationTokenRepository::class),
        );
        $synchronizer->setLockFactory(new LockFactory(new InMemoryStore()));
        $synchronizer->setPusherManager(self::getService(PusherManager::class));

        return $synchronizer;
    }

    /**
     * @param Asset[] $assets
     */
    private function createBasketData(array $assets): IntegrationData
    {
        $em = self::getEntityManager();
        $basket = $this->createBasket(['ownerId' => self::OWNER, 'name' => 'Selection']);
        foreach (array_values($assets) as $position => $asset) {
            $this->addToBasket($basket, $asset, $position);
        }

        $data = new IntegrationData();
        $data->setIntegration($em->find(WorkspaceIntegration::class, $this->integration->getId()));
        $data->setUserId(self::OWNER);
        $data->setObject($basket);
        $data->setName('publication_id');
        $data->setValue(json_encode(['id' => $this->publicationId, 'ignoreRoi' => false], JSON_THROW_ON_ERROR));
        $em->persist($data);
        $em->flush();

        return $data;
    }

    private function addToBasket(Basket $basket, Asset $asset, int $position): BasketAsset
    {
        $em = self::getEntityManager();
        $basketAsset = new BasketAsset();
        $basketAsset->setBasket($em->find(Basket::class, $basket->getId()));
        $basketAsset->setAsset($em->find(Asset::class, $asset->getId()));
        $basketAsset->setOwnerId(self::OWNER);
        $basketAsset->setPosition($position);
        $em->persist($basketAsset);
        $em->flush();

        return $basketAsset;
    }

    private function synchronize(IntegrationData $data): void
    {
        $em = self::getEntityManager();
        $em->clear();
        $this->createSynchronizer()->synchronize($em->find(IntegrationData::class, $data->getId()));
    }

    public function testPublishesTheBasketAssets(): void
    {
        $photo = $this->createPublishableAsset($this->workspace, self::OWNER, 'photo.jpg');
        $other = $this->createPublishableAsset($this->workspace, self::OWNER, 'other.jpg');
        $data = $this->createBasketData([$photo, $other]);

        $this->synchronize($data);

        $published = array_values($this->api->getPublicationAssets($this->publicationId));
        $this->assertCount(2, $published);
        $this->assertSame(['photo.jpg', 'other.jpg'], array_column($published, 'title'));
        $this->assertSame([$photo->getId(), $other->getId()], array_column($published, 'assetId'));
        foreach ($published as $exposeAsset) {
            $this->assertTrue($exposeAsset['uploaded']);
            $annotations = json_decode((string) $exposeAsset['clientAnnotations'], true);
            $this->assertSame(['basketAssetId', 'fileId'], array_keys($annotations));
            $this->assertSame(['preview', 'thumbnail'], array_keys($this->api->getSubDefinitions($exposeAsset['id'])));
        }
    }

    public function testSynchronizingAgainSendsNothing(): void
    {
        $data = $this->createBasketData([$this->createPublishableAsset($this->workspace, self::OWNER, 'photo.jpg')]);
        $this->synchronize($data);
        $this->api->requests = [];

        $this->synchronize($data);

        $this->assertSame([], $this->api->getWrites());
        $this->assertCount(1, $this->api->getPublicationAssets($this->publicationId));
    }

    public function testATitleChangeOnlyPatchesTheAsset(): void
    {
        $asset = $this->createPublishableAsset($this->workspace, self::OWNER, 'photo.jpg');
        $data = $this->createBasketData([$asset]);
        $this->synchronize($data);
        $this->api->requests = [];

        $em = self::getEntityManager();
        $em->find(File::class, $em->find(Asset::class, $asset->getId())->getSource()->getId())->setOriginalName('renamed.jpg');
        $em->flush();
        $this->synchronize($data);

        $this->assertSame(['PATCH /assets/{id}'], $this->api->getWrites());
        $this->assertSame(['renamed.jpg'], array_column($this->api->getPublicationAssets($this->publicationId), 'title'));
    }

    public function testRemovesTheAssetsRemovedFromTheBasket(): void
    {
        $kept = $this->createPublishableAsset($this->workspace, self::OWNER, 'kept.jpg');
        $removed = $this->createPublishableAsset($this->workspace, self::OWNER, 'removed.jpg');
        $data = $this->createBasketData([$kept, $removed]);
        // Published by an automation: not a basket item
        $this->api->addAsset($this->publicationId, ['automationId' => 'a1', 'assetId' => $kept->getId(), 'fileId' => 'f1'], 'automation');
        $this->synchronize($data);

        $em = self::getEntityManager();
        foreach ($em->getRepository(BasketAsset::class)->findBy(['asset' => $removed->getId()]) as $basketAsset) {
            $em->remove($basketAsset);
        }
        $em->flush();
        $this->synchronize($data);

        $this->assertEqualsCanonicalizing(['kept.jpg', 'automation'], array_column($this->api->getPublicationAssets($this->publicationId), 'title'));
    }
}
