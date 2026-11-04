<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Rendition;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Asset\AssetExportProcess;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\AssetExport;
use App\Model\ExportStatusEnum;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * POST /asset-exports, GET /asset-exports/{id}.
 */
final class AssetExportApiTest extends AbstractDataboxTestCase
{
    use RenditionTestTrait;

    private const string OWNER = KeycloakClientTestMock::USER_UID;
    private const string OTHER = KeycloakClientTestMock::OTHER_USER_UID;

    private function interceptP1(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p1');

        return $transport->intercept();
    }

    /**
     * @return array{0: string, 1: string} asset ID, rendition definition ID
     */
    private function createExportableAsset(): array
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::OWNER);
        $asset = $this->createAsset(['workspace' => $ws, 'ownerId' => self::OWNER]);
        $main = $defaults->renditionDefinitions['main'];
        $this->createAssetRendition($asset, $main, $this->createUrlFile($ws));

        return [$asset->getId(), $main->getId()];
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->jsonRequest('POST', '/asset-exports', null, ['assets' => ['x'], 'renditions' => ['y']]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateValidation(): void
    {
        $response = $this->jsonRequest('POST', '/asset-exports', self::OWNER, ['assets' => [], 'renditions' => []]);
        $this->assertResponseStatusCodeSame(422);
        $paths = array_column($response->toArray(false)['violations'], 'propertyPath');
        $this->assertEqualsCanonicalizing(['assets', 'renditions'], $paths);

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, []);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateIsDeniedOnAnUnreadableAsset(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();

        $this->jsonRequest('POST', '/asset-exports', self::OTHER, ['assets' => [$assetId], 'renditions' => [$definitionId]]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(0, self::getEntityManager()->getRepository(AssetExport::class)->count([]));
    }

    public function testCreateWithAnUnknownAssetIsNotFound(): void
    {
        $this->markTestIncomplete('BUG: AssetExportProcessor uses DoctrineUtil::findStrict() without $throw404 (src/Api/Processor/AssetExportProcessor.php:37): an unknown asset ID raises an \InvalidArgumentException, i.e. a 500.');

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => ['f1b4b4a8-0000-4000-8000-000000000000'],
            'renditions' => ['f1b4b4a8-0000-4000-8000-000000000001'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateQueuesTheExport(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $transport = $this->interceptP1();

        $response = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'asset-export',
            'status' => ExportStatusEnum::Pending->value,
            'owner' => [
                'id' => self::OWNER,
                'username' => 'user',
            ],
        ]);
        $data = $response->toArray();
        $this->assertArrayNotHasKey('downloadUrl', $data, 'No archive yet');
        $this->assertArrayNotHasKey('assets', $data, 'Write-only');
        $this->assertArrayNotHasKey('renditions', $data, 'Write-only');

        $messages = array_values(array_filter(
            $transport->getSent(),
            fn ($envelope): bool => $envelope->getMessage() instanceof AssetExportProcess,
        ));
        $this->assertCount(1, $messages);
        $this->assertSame($data['id'], $messages[0]->getMessage()->id);

        /** @var AssetExport $export */
        $export = self::getEntityManager()->find(AssetExport::class, $data['id']);
        $this->assertSame([$assetId], $export->getAssets());
        $this->assertSame([$definitionId], $export->getRenditions());
        $this->assertSame(self::OWNER, $export->getOwnerId());
    }

    public function testGetExport(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, null);
        $this->assertResponseStatusCodeSame(401);

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OWNER);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/asset-exports/'.$exportId,
            'id' => $exportId,
            'status' => ExportStatusEnum::Pending->value,
            'owner' => ['id' => self::OWNER],
        ]);

        $this->jsonRequest('GET', '/asset-exports/f1b4b4a8-0000-4000-8000-000000000000', self::OWNER);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testReadyExportExposesASignedDownloadUrl(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $em = self::getEntityManager();
        /** @var AssetExport $export */
        $export = $em->find(AssetExport::class, $exportId);
        $export->setStatus(ExportStatusEnum::Ready);
        $export->setPath('exports/archive.zip');
        $em->flush();

        $data = $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OWNER)->toArray();
        $this->assertSame(ExportStatusEnum::Ready->value, $data['status']);
        $this->assertStringContainsString('exports/archive.zip', $data['downloadUrl']);
    }

    /**
     * @return array{0: string[], 1: string} the basket item IDs (an asset twice, with and
     *                                       without a clip, then another one), the rendition definition ID
     */
    private function createBasketItems(string $ownerId = self::OWNER): array
    {
        [$ws, $defaults] = $this->createWorkspaceWithDefaults('ws-a', self::OWNER);
        $main = $defaults->renditionDefinitions['main'];
        $em = self::getEntityManager();

        $first = $this->createAsset(['workspace' => $ws, 'ownerId' => self::OWNER]);
        $this->createAssetRendition($first, $main, $this->createUrlFile($ws));
        $second = $this->createAsset(['workspace' => $ws, 'ownerId' => self::OWNER]);
        $this->createAssetRendition($second, $main, $this->createUrlFile($ws));

        $basket = new Basket();
        $basket->setName('Basket');
        $basket->setOwnerId($ownerId);
        $em->persist($basket);

        $ids = [];
        foreach ([
            [$first, ['timeRange' => ['s' => 1, 'e' => 2]]],
            [$first, null],
            [$second, null],
        ] as $position => [$asset, $clip]) {
            $item = new BasketAsset();
            $item->setBasket($basket);
            $item->setAsset($asset);
            $item->setOwnerId($ownerId);
            $item->setPosition($position);
            $item->setClip($clip);
            $em->persist($item);
            $em->flush();
            $ids[] = $item->getId();
        }

        return [$ids, $main->getId()];
    }

    public function testCreateFromBasketItems(): void
    {
        [$itemIds, $definitionId] = $this->createBasketItems();
        $transport = $this->interceptP1();

        $response = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'basketAssets' => $itemIds,
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertArrayNotHasKey('basketAssets', $response->toArray(), 'Write-only');
        $this->assertCount(1, array_filter(
            $transport->getSent(),
            fn ($envelope): bool => $envelope->getMessage() instanceof AssetExportProcess,
        ));

        $em = self::getEntityManager();
        /** @var AssetExport $export */
        $export = $em->find(AssetExport::class, $response->toArray()['id']);
        $this->assertSame($itemIds, $export->getBasketAssets());
        // The assets of the items, once each, in the order of the items
        $assetIds = array_map(static fn (string $id): string => $em->find(BasketAsset::class, $id)->getAsset()->getId(), $itemIds);
        $this->assertSame(array_values(array_unique($assetIds)), $export->getAssets());
        $this->assertCount(2, $export->getAssets());
    }

    public function testCreateFromBasketItemsValidation(): void
    {
        $this->jsonRequest('POST', '/asset-exports', self::OWNER, ['basketAssets' => [], 'renditions' => ['x']]);
        $this->assertResponseStatusCodeSame(422);

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, ['basketAssets' => ['not-an-id'], 'renditions' => ['x']]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateFromAnUnknownBasketItemIsNotFound(): void
    {
        $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'basketAssets' => ['f1b4b4a8-0000-4000-8000-000000000000'],
            'renditions' => ['f1b4b4a8-0000-4000-8000-000000000001'],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateFromBasketItemsRequiresToReadTheBasket(): void
    {
        [$itemIds, $definitionId] = $this->createBasketItems(self::OTHER);
        $this->interceptP1();

        $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'basketAssets' => $itemIds,
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, self::getEntityManager()->getRepository(AssetExport::class)->count([]));

        $basket = self::getEntityManager()->find(BasketAsset::class, $itemIds[0])->getBasket();
        $this->grantUserOnObject(self::OWNER, $basket, PermissionInterface::VIEW);
        $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'basketAssets' => $itemIds,
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateFromBasketItemsRequiresToReadTheAssets(): void
    {
        [$itemIds, $definitionId] = $this->createBasketItems();
        $this->interceptP1();

        // OTHER owns a basket holding assets of a workspace they cannot read
        $em = self::getEntityManager();
        $basket = $em->find(BasketAsset::class, $itemIds[0])->getBasket();
        $this->grantUserOnObject(self::OTHER, $basket, PermissionInterface::VIEW);

        $this->jsonRequest('POST', '/asset-exports', self::OTHER, [
            'basketAssets' => $itemIds,
            'renditions' => [$definitionId],
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testExportOfAnotherUserIsNotReadable(): void
    {
        [$assetId, $definitionId] = $this->createExportableAsset();
        $this->interceptP1();
        $exportId = $this->jsonRequest('POST', '/asset-exports', self::OWNER, [
            'assets' => [$assetId],
            'renditions' => [$definitionId],
        ])->toArray()['id'];

        $this->jsonRequest('GET', '/asset-exports/'.$exportId, self::OTHER);
        $this->assertResponseStatusCodeSame(403);
    }
}
