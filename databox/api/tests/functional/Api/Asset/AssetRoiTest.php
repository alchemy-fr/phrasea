<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Asset;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\MessengerBundle\Transport\TestTransport;
use App\Consumer\Handler\Asset\AssetRoiUpdate;
use App\Consumer\Handler\Asset\AssetRoiUpdateHandler;
use App\Consumer\Handler\Basket\BasketUpdate;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Tests\Functional\AbstractDataboxTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Region of interest of the assets: PATCH /assets/{id} {"roi": …}.
 */
final class AssetRoiTest extends AbstractDataboxTestCase
{
    use AssetApiTestTrait;

    private const array CROP = ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5];

    private function createAssetWithSource(string $type, string $ownerId = self::OWNER): Asset
    {
        $workspace = $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => $ownerId, 'no_flush' => true]);
        $asset->setSource($this->createFile($workspace, ['type' => $type]));
        self::getEntityManager()->flush();

        return $asset;
    }

    private function interceptP2(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p2');

        return $transport->intercept();
    }

    public function testOwnerSetsTheRoiOfAVideo(): void
    {
        $asset = $this->createAssetWithSource('video/mp4');

        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, [
            'roi' => [
                'timeRanges' => [['s' => 50, 'e' => 61.2], ['s' => 12, 'e' => 30.5], ['s' => 25, 'e' => 32]],
                'crop' => self::CROP,
            ],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $expected = [
            'timeRanges' => [['s' => 12.0, 'e' => 32.0], ['s' => 50.0, 'e' => 61.2]],
            'crop' => self::CROP,
        ];
        $this->assertEquals($expected, $data['roi']);
        $this->assertEquals($expected, $this->reloadAsset($asset->getId())->getRoi());

        // An omitted key leaves it unchanged
        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['trackingId' => 'x'])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertEquals($expected, $data['roi']);
    }

    public function testLegacySingleRangeIsAccepted(): void
    {
        $asset = $this->createAssetWithSource('audio/mpeg');

        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, [
            'roi' => ['timeRange' => ['s' => 1, 'e' => 2]],
        ])->toArray();

        $this->assertResponseIsSuccessful();
        $this->assertEquals(['timeRanges' => [['s' => 1, 'e' => 2]]], $data['roi']);
    }

    public function testNullOrEmptyRemovesTheRoi(): void
    {
        $assets = [$this->createAssetWithSource('image/png'), $this->createAssetWithSource('image/png')];
        foreach ([null, []] as $i => $empty) {
            $asset = $assets[$i];
            $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['crop' => self::CROP]]);
            $this->assertResponseIsSuccessful();

            $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => $empty])->toArray();
            $this->assertResponseIsSuccessful();
            $this->assertArrayNotHasKey('roi', $data);
            $this->assertNull($this->reloadAsset($asset->getId())->getRoi());
        }
    }

    public function testRoiRequiresToEditTheAsset(): void
    {
        $asset = $this->createAssetWithSource('image/png');
        $this->addUserOnWorkspace(self::OTHER, $asset->getWorkspaceId());
        $this->grantUserOnObject(self::OTHER, $asset, PermissionInterface::VIEW);

        $this->request('GET', '/assets/'.$asset->getId(), self::OTHER);
        $this->assertResponseIsSuccessful();

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OTHER, ['roi' => ['crop' => self::CROP]]);
        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->reloadAsset($asset->getId())->getRoi());
    }

    /**
     * @return iterable<string, array{string|null, array}>
     */
    public static function provideInapplicableRois(): iterable
    {
        $range = ['timeRanges' => [['s' => 1, 'e' => 2]]];
        $crop = ['crop' => self::CROP];

        yield 'range on an image' => ['image/jpeg', $range];
        yield 'crop on an audio' => ['audio/mpeg', $crop];
        yield 'crop on a SVG' => ['image/svg+xml', $crop];
        yield 'range on a document' => ['application/pdf', $range];
        yield 'no source' => [null, $crop];
    }

    #[DataProvider('provideInapplicableRois')]
    public function testInapplicableRoiIsRejected(?string $type, array $roi): void
    {
        if (null === $type) {
            $this->createOwnedWorkspace();
            $asset = $this->createAsset(['ownerId' => self::OWNER]);
        } else {
            $asset = $this->createAssetWithSource($type);
        }

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => $roi]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->reloadAsset($asset->getId())->getRoi());
    }

    public function testInvalidRoiIsRejected(): void
    {
        $asset = $this->createAssetWithSource('video/mp4');

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['timeRanges' => [['s' => 3, 'e' => 1]]]]);
        $this->assertResponseStatusCodeSame(422);

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['crop' => ['x' => 0.8, 'y' => 0, 'w' => 0.5, 'h' => 0.5]]]);
        $this->assertResponseStatusCodeSame(422);

        $tooMany = [];
        for ($i = 0; $i < 51; ++$i) {
            $tooMany[] = ['s' => $i * 2, 'e' => $i * 2 + 1];
        }
        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['timeRanges' => $tooMany]]);
        $this->assertResponseStatusCodeSame(422);

        $this->assertNull($this->reloadAsset($asset->getId())->getRoi());
    }

    public function testRoiIsRead(): void
    {
        $asset = $this->createAssetWithSource('image/png');
        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['crop' => self::CROP]]);
        $this->assertResponseIsSuccessful();

        $data = $this->request('GET', '/assets/'.$asset->getId(), self::OWNER)->toArray();
        $this->assertEquals(['crop' => self::CROP], $data['roi']);
    }

    public function testSourceChangeRemovesTheRoi(): void
    {
        $asset = $this->createAssetWithSource('image/png');
        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['crop' => self::CROP]]);
        $this->assertResponseIsSuccessful();

        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, [
            'sourceFile' => ['url' => 'https://example.com/new.jpg'],
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertArrayNotHasKey('roi', $data);
        $this->assertNull($this->reloadAsset($asset->getId())->getRoi());

        // Unless given in the same request, applied to the new source
        $data = $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, [
            'sourceFile' => ['url' => 'https://example.com/new.mp4', 'type' => 'video/mp4'],
            'roi' => ['timeRanges' => [['s' => 1, 'e' => 2]]],
        ])->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertSame('video/mp4', $data['source']['type']);
        $this->assertEquals(['timeRanges' => [['s' => 1, 'e' => 2]]], $data['roi']);
    }

    public function testRoiChangeUpdatesTheBasketsOfTheAsset(): void
    {
        $asset = $this->createAssetWithSource('image/png');
        $transport = $this->interceptP2();

        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['roi' => ['crop' => self::CROP]]);
        $this->assertResponseIsSuccessful();
        $this->assertSame([$asset->getId()], $this->sentRoiUpdates($transport));

        // Unchanged
        $transport->reset();
        $this->request('PATCH', '/assets/'.$asset->getId(), self::OWNER, ['trackingId' => 'x']);
        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->sentRoiUpdates($transport));
    }

    public function testRoiUpdateHandlerUpdatesEachBasketOnce(): void
    {
        $this->createOwnedWorkspace();
        $asset = $this->createAsset(['ownerId' => self::OWNER]);
        $other = $this->createAsset(['ownerId' => self::OWNER]);
        $twice = $this->createBasket(['ownerId' => self::OWNER]);
        $once = $this->createBasket(['ownerId' => self::OWNER]);
        $without = $this->createBasket(['ownerId' => self::OWNER]);

        $em = self::getEntityManager();
        foreach ([[$twice, $asset], [$twice, $asset], [$once, $asset], [$once, $other], [$without, $other]] as $position => [$basket, $item]) {
            $basketAsset = new BasketAsset();
            $basketAsset->setBasket($basket);
            $basketAsset->setAsset($item);
            $basketAsset->setOwnerId(self::OWNER);
            $basketAsset->setPosition($position);
            $em->persist($basketAsset);
        }
        $em->flush();

        $transport = $this->interceptP2();
        self::getService(AssetRoiUpdateHandler::class)(new AssetRoiUpdate($asset->getId()));

        $basketIds = array_map(
            static fn ($envelope): string => $envelope->getMessage()->getId(),
            array_values(array_filter($transport->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof BasketUpdate)),
        );
        $this->assertEqualsCanonicalizing([$twice->getId(), $once->getId()], $basketIds);
    }

    /**
     * @return string[] the asset IDs
     */
    private function sentRoiUpdates(InMemoryTransport $transport): array
    {
        return array_map(
            static fn ($envelope): string => $envelope->getMessage()->assetId,
            array_values(array_filter($transport->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof AssetRoiUpdate)),
        );
    }
}
