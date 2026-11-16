<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\CoreBundle\Pusher\PusherManager;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Automation\UserContextSwitcher;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Entity\Core\File;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationManager;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use App\Repository\Integration\IntegrationTokenRepository;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

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
            self::getEntityManager(),
            new NullLogger(),
            self::getService(UserContextSwitcher::class),
            self::getService(Security::class),
        );
        $synchronizer->setLockFactory(new LockFactory(new InMemoryStore()));
        $synchronizer->setPusherManager(self::getService(PusherManager::class));

        return $synchronizer;
    }

    /**
     * @param Asset[] $assets
     */
    private function createBasketData(array $assets, string $userId = self::OWNER): IntegrationData
    {
        $em = self::getEntityManager();
        $basket = $this->createBasket(['ownerId' => self::OWNER, 'name' => 'Selection']);
        foreach (array_values($assets) as $position => $asset) {
            $this->addToBasket($basket, $asset, $position);
        }

        $data = new IntegrationData();
        $data->setIntegration($em->find(WorkspaceIntegration::class, $this->integration->getId()));
        $data->setUserId($userId);
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

    public function testPublishesAFileWithoutOriginalNameUnderItsExtension(): void
    {
        $this->refuseFilesWithoutExtension();
        $data = $this->createBasketData([$this->createPublishableAsset($this->workspace, self::OWNER, null, 'application/pdf', 'pdf')]);

        $this->synchronize($data);

        $this->assertSame([['filename' => 'file.pdf', 'type' => 'application/pdf']], $this->api->uploads);
        $this->assertCount(1, $this->api->getPublicationAssets($this->publicationId));
        $this->assertSame([], $this->readPublicationData($data)['skipped']);
    }

    public function testAddsItsExtensionToAnOriginalNameWithout(): void
    {
        $this->refuseFilesWithoutExtension();
        $data = $this->createBasketData([$this->createPublishableAsset($this->workspace, self::OWNER, 'press-release', 'application/pdf', 'pdf')]);

        $this->synchronize($data);

        $this->assertSame(['press-release.pdf'], array_column($this->api->uploads, 'filename'));
        $this->assertCount(1, $this->api->getPublicationAssets($this->publicationId));
    }

    public function testSetsAsideTheAssetsRefusedByExpose(): void
    {
        $refused = $this->createPublishableAsset($this->workspace, self::OWNER, 'refused.jpg');
        $data = $this->createBasketData([
            $this->createPublishableAsset($this->workspace, self::OWNER, 'first.jpg'),
            $refused,
            $this->createPublishableAsset($this->workspace, self::OWNER, 'last.jpg'),
        ]);
        $this->api->refuseUploads(fn (string $filename): ?string => 'refused.jpg' === $filename ? 'File type "image/jpeg" is not allowed' : null);

        $this->synchronize($data);

        $this->assertSame(['first.jpg', 'last.jpg'], array_column(array_values($this->api->getPublicationAssets($this->publicationId)), 'title'));
        $basketAsset = self::getEntityManager()->getRepository(BasketAsset::class)->findOneBy(['asset' => $refused->getId()]);
        $publication = $this->readPublicationData($data);
        $this->assertSame([[
            'assetId' => $refused->getId(),
            'basketAssetId' => $basketAsset->getId(),
            'title' => 'refused.jpg',
            'reason' => 'File type "image/jpeg" is not allowed',
        ]], $publication['skipped']);
        $this->assertSame(1, $publication['skippedTotal']);
        $this->assertSame($this->publicationId, $publication['id']);
    }

    public function testASynchronizationWithoutRefusalClearsTheSetAsideAssets(): void
    {
        $data = $this->createBasketData([
            $this->createPublishableAsset($this->workspace, self::OWNER, 'first.jpg'),
            $this->createPublishableAsset($this->workspace, self::OWNER, 'refused.jpg'),
        ]);
        $this->api->refuseUploads(fn (string $filename): ?string => 'refused.jpg' === $filename ? 'Refused' : null);
        $this->synchronize($data);
        $this->assertSame(1, $this->readPublicationData($data)['skippedTotal']);

        $this->api->refuseUploads(fn (): ?string => null);
        $this->synchronize($data);

        $this->assertEqualsCanonicalizing(['first.jpg', 'refused.jpg'], array_column($this->api->getPublicationAssets($this->publicationId), 'title'));
        $publication = $this->readPublicationData($data);
        $this->assertSame([], $publication['skipped']);
        $this->assertSame(0, $publication['skippedTotal']);
    }

    public function testAnUnauthorizedUploadStillStopsTheSynchronization(): void
    {
        $data = $this->createBasketData([
            $this->createPublishableAsset($this->workspace, self::OWNER, 'first.jpg'),
            $this->createPublishableAsset($this->workspace, self::OWNER, 'second.jpg'),
        ]);
        $this->api->failures['POST /uploads'] = 401;

        try {
            $this->synchronize($data);
            $this->fail('The synchronization should have failed');
        } catch (ClientExceptionInterface $e) {
            $this->assertSame(401, $e->getResponse()->getStatusCode());
        }

        $this->assertSame([], $this->api->getPublicationAssets($this->publicationId));
        $this->assertCount(1, array_keys($this->api->requests, 'POST /uploads', true));
    }

    public function testSetsAsideTheAssetsTheUserCannotRead(): void
    {
        $otherWorkspace = $this->createWorkspace(['ownerId' => KeycloakClientTestMock::OTHER_USER_UID, 'slug' => 'other-workspace']);
        $unreadable = $this->createPublishableAsset($otherWorkspace, KeycloakClientTestMock::OTHER_USER_UID, 'unreadable.jpg');
        $data = $this->createBasketData([
            $unreadable,
            $this->createPublishableAsset($this->workspace, self::OWNER, 'readable.jpg'),
        ]);

        $this->synchronize($data);

        $this->assertSame(['readable.jpg'], array_column(array_values($this->api->getPublicationAssets($this->publicationId)), 'title'));
        $basketAsset = self::getEntityManager()->getRepository(BasketAsset::class)->findOneBy(['asset' => $unreadable->getId()]);
        $publication = $this->readPublicationData($data);
        $this->assertSame([[
            'assetId' => $unreadable->getId(),
            'basketAssetId' => $basketAsset->getId(),
            'title' => null,
            'reason' => 'Not allowed to read the asset',
        ]], $publication['skipped']);
        $this->assertSame(1, $publication['skippedTotal']);
    }

    public function testUnpublishesAnAssetMovedToTheTrash(): void
    {
        $trashed = $this->createPublishableAsset($this->workspace, self::OWNER, 'trashed.jpg');
        $data = $this->createBasketData([
            $this->createPublishableAsset($this->workspace, self::OWNER, 'kept.jpg'),
            $trashed,
        ]);
        $this->synchronize($data);
        $this->assertCount(2, $this->api->getPublicationAssets($this->publicationId));

        // The owner of the asset may still read it: the trash is checked explicitly
        $em = self::getEntityManager();
        $em->find(Asset::class, $trashed->getId())->setDeletedAt(new \DateTimeImmutable());
        $em->flush();
        $this->synchronize($data);

        $this->assertSame(['kept.jpg'], array_column(array_values($this->api->getPublicationAssets($this->publicationId)), 'title'));
        $publication = $this->readPublicationData($data);
        $this->assertSame([$trashed->getId()], array_column($publication['skipped'], 'assetId'));
        $this->assertSame(['The asset is deleted'], array_column($publication['skipped'], 'reason'));
        $this->assertSame(1, $publication['skippedTotal']);
    }

    #[DataProvider('getNotAcceptedStatuses')]
    public function testDoesNotPublishAnAssetNotAccepted(AssetStatusEnum $status): void
    {
        $notAccepted = $this->createPublishableAsset($this->workspace, self::OWNER, 'not-accepted.jpg');
        $em = self::getEntityManager();
        $em->find(Asset::class, $notAccepted->getId())->setStatus($status);
        $em->flush();
        $data = $this->createBasketData([
            $notAccepted,
            $this->createPublishableAsset($this->workspace, self::OWNER, 'accepted.jpg'),
        ]);

        $this->synchronize($data);

        $this->assertSame(['accepted.jpg'], array_column(array_values($this->api->getPublicationAssets($this->publicationId)), 'title'));
        $publication = $this->readPublicationData($data);
        $this->assertSame([$notAccepted->getId()], array_column($publication['skipped'], 'assetId'));
        $this->assertSame(['The asset is not accepted (pending analysis or quarantined)'], array_column($publication['skipped'], 'reason'));
    }

    public static function getNotAcceptedStatuses(): array
    {
        return [
            'quarantined' => [AssetStatusEnum::Quarantined],
            'pending' => [AssetStatusEnum::Pending],
        ];
    }

    public function testAnUnavailableUserStopsTheSynchronizationWithoutTouchingThePublication(): void
    {
        $this->createExposeToken($this->integration, KeycloakClientTestMock::DISABLED_USER_UID);
        $data = $this->createBasketData(
            [$this->createPublishableAsset($this->workspace, self::OWNER, 'photo.jpg')],
            KeycloakClientTestMock::DISABLED_USER_UID,
        );
        // Removed by the cleaning if the synchronization went on: no longer in the basket
        $this->api->addAsset($this->publicationId, ['basketAssetId' => 'former-item'], 'former.jpg');

        try {
            $this->synchronize($data);
            $this->fail('The synchronization should have failed');
        } catch (AutomationOwnerUnavailableException) {
        }

        $this->assertSame([], $this->api->requests);
        $this->assertSame(['former.jpg'], array_column(array_values($this->api->getPublicationAssets($this->publicationId)), 'title'));
    }

    /**
     * As the file validation of Expose, which requires an extension matching the type.
     */
    private function refuseFilesWithoutExtension(): void
    {
        $this->api->refuseUploads(fn (string $filename, ?string $type): ?string => '' === pathinfo($filename, PATHINFO_EXTENSION)
            ? sprintf('File type "%s" with extension "" is not allowed', $type)
            : null);
    }

    private function readPublicationData(IntegrationData $data): array
    {
        $em = self::getEntityManager();
        $em->clear();

        return ExposeIntegration::readPublicationData($em->find(IntegrationData::class, $data->getId()));
    }
}
