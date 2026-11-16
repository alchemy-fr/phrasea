<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetFileVersion;
use App\Entity\Core\File;
use App\Entity\Core\FileDuplicate;
use App\Entity\Core\Workspace;
use App\Tests\Functional\AbstractSearchTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Attribute filter rules hide assets from the listings that are not served by the
 * search: duplicates, file usages and basket items.
 */
class AttributeFilterRuleOutsideSearchTest extends AbstractSearchTestCase
{
    private const string USER = KeycloakClientTestMock::USER_UID;
    private const string ADMIN = KeycloakClientTestMock::ADMIN_UID;

    public function testDuplicatesHideTheAssetsFilteredOutByARule(): void
    {
        $workspace = $this->createPublicWorkspace();
        $origin = $this->createAssetWithSource($workspace, 'Origin', ['visible']);
        $visible = $this->createAssetWithSource($workspace, 'Visible', ['visible']);
        $hidden = $this->createAssetWithSource($workspace, 'Hidden', []);

        $em = self::getEntityManager();
        foreach ([$visible, $hidden] as $duplicate) {
            $link = new FileDuplicate();
            $link->setFile($origin->getSource());
            $link->setDuplicateFile($duplicate->getSource());
            $link->setAnalyzer('checksum');
            $em->persist($link);
        }
        $em->flush();

        $fileId = $origin->getSource()->getId();
        $originId = $origin->getId();
        $visibleId = $visible->getId();
        $hiddenId = $hidden->getId();
        $this->hideUntaggedAssetsFromUser($workspace, 'visible');

        foreach (['/files/'.$fileId.'/duplicates', '/assets/'.$originId.'/duplicates'] as $uri) {
            $this->assertSame([$visibleId], $this->getDuplicateIds($uri, self::USER), $uri);

            $expected = [$visibleId, $hiddenId];
            sort($expected);
            $this->assertSame($expected, $this->getDuplicateIds($uri, self::ADMIN), $uri);
        }
    }

    public function testFileUsagesHideTheAssetsFilteredOutByARule(): void
    {
        $workspace = $this->createPublicWorkspace();
        $hidden = $this->createAssetWithSource($workspace, 'Hidden', []);
        $visible = $this->createAssetWithSource($workspace, 'Visible', ['visible']);

        $em = self::getEntityManager();
        $version = new AssetFileVersion();
        $version->setAsset($visible);
        $version->setFile($hidden->getSource());
        $version->setVersionName('v1');
        $em->persist($version);
        $em->flush();

        $fileId = $hidden->getSource()->getId();
        $visibleId = $visible->getId();
        $hiddenId = $hidden->getId();
        $this->hideUntaggedAssetsFromUser($workspace, 'visible');

        $usages = $this->getDataFromResponse($this->request('GET', '/files/'.$fileId, self::USER), 200)['usages'];
        $this->assertSame([['version', $visibleId]], array_map(fn (array $u): array => [$u['type'], $u['assetId']], $usages));

        $usages = $this->getDataFromResponse($this->request('GET', '/files/'.$fileId, self::ADMIN), 200)['usages'];
        $this->assertSame([
            ['source', $hiddenId],
            ['version', $visibleId],
        ], array_map(fn (array $u): array => [$u['type'], $u['assetId']], $usages));
    }

    public function testBasketItemsRenderAFilteredOutAssetAsAStub(): void
    {
        $workspace = $this->createPublicWorkspace();
        $visible = $this->createAssetWithSource($workspace, 'Visible', ['visible']);
        $hidden = $this->createAssetWithSource($workspace, 'Hidden', []);

        $em = self::getEntityManager();
        $basket = $this->createBasket(['name' => 'B', 'ownerId' => self::USER]);
        foreach ([$visible, $hidden] as $position => $asset) {
            $item = new BasketAsset();
            $item->setBasket($basket);
            $item->setAsset($asset);
            $item->setOwnerId(self::USER);
            $item->setPosition($position);
            $em->persist($item);
        }
        $em->flush();

        $basketId = $basket->getId();
        $visibleId = $visible->getId();
        $hiddenId = $hidden->getId();
        $this->hideUntaggedAssetsFromUser($workspace, 'visible');

        $data = $this->getDataFromResponse($this->request('GET', '/baskets/'.$basketId.'/assets', self::USER), 200);
        $this->assertSame(2, $data['totalItems']);
        $this->assertCount(2, $data['member']);

        $assets = array_column(array_column($data['member'], 'asset'), null, 'id');
        $this->assertSame([$visibleId, $hiddenId], array_keys($assets));

        foreach (['source', 'createdAt', 'workspace'] as $key) {
            $this->assertArrayHasKey($key, $assets[$visibleId], $key);
            $this->assertArrayNotHasKey($key, $assets[$hiddenId], $key);
        }
        $this->assertArrayNotHasKey('attributes', $assets[$hiddenId]);
    }

    private function createPublicWorkspace(): Workspace
    {
        return $this->createWorkspace([
            'public' => true,
            'no_flush' => true,
        ]);
    }

    /**
     * @param string[] $tags
     */
    private function createAssetWithSource(Workspace $workspace, string $name, array $tags): Asset
    {
        $em = self::getEntityManager();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_URL);
        $file->setPath('https://example.com/'.uniqid().'.jpg');
        $file->setPathPublic(true);
        $file->setType('image/jpeg');
        $file->setExtension('jpg');
        $file->setOriginalName('photo.jpg');
        $file->setSize(1234);
        $em->persist($file);

        $asset = $this->createAsset([
            'workspace' => $workspace,
            'name' => $name,
            'public' => true,
            'tags' => $tags,
            'no_flush' => true,
        ]);
        $asset->setSource($file);
        $em->flush();

        return $asset;
    }

    private function hideUntaggedAssetsFromUser(Workspace $workspace, string $tagName): void
    {
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $tag = $this->findOrCreateTagByName($tagName, $workspace);
        self::getAttributeFilterManager()->saveRule(
            $workspace,
            [self::USER],
            [],
            sprintf('@tag = "%s"', $tag->getId()),
        );
    }

    /**
     * @return string[] sorted
     */
    private function getDuplicateIds(string $uri, string $userId): array
    {
        $data = $this->getDataFromResponse($this->request('GET', $uri, $userId), 200);
        $ids = array_map(fn (array $d): string => $d['asset']['id'], $data['duplicates']);
        sort($ids);

        return $ids;
    }

    private function request(string $method, string $uri, string $userId): ResponseInterface
    {
        self::getEntityManager()->clear();

        return static::createClient()->request($method, $uri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
            ],
        ]);
    }
}
