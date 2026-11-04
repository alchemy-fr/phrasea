<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\File;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

class ShareTest extends AbstractDataboxTestCase
{
    public function testCreateMultiAssetShare(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $fooIri = $this->findIriBy(Asset::class, ['key' => 'foo']);
        $barIri = $this->findIriBy(Asset::class, ['key' => 'bar']);

        $response = $client->request('POST', '/shares', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assets' => [$fooIri, $barIri],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertCount(2, $data['assets']);
        $this->assertNotEmpty($data['token']);
    }

    public function testCreateShareWithoutAssetsIsRejected(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $client->request('POST', '/shares', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assets' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPublicShareAccessWithToken(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $fooIri = $this->findIriBy(Asset::class, ['key' => 'foo']);
        $barIri = $this->findIriBy(Asset::class, ['key' => 'bar']);

        $response = $client->request('POST', '/shares', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assets' => [$fooIri, $barIri],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $id = $data['id'];
        $token = $data['token'];

        $response = $client->request('GET', sprintf('/shares/%s/public?token=%s', $id, $token));
        $this->assertResponseIsSuccessful();
        $publicData = $response->toArray();
        $this->assertCount(2, $publicData['assets']);

        $client->request('GET', sprintf('/shares/%s/public?token=invalid-token', $id));
        $this->assertResponseStatusCodeSame(401);
    }

    public function testShareOfUnauthorizedUserIsDenied(): void
    {
        self::enableFixtures();
        $client = static::createClient();
        $fooIri = $this->findIriBy(Asset::class, ['key' => 'foo']);

        $client->request('POST', '/shares', [
            'headers' => [
                // OTHER_USER does not own the assets, is not the workspace owner and has no SHARE ACL
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::OTHER_USER_UID),
            ],
            'json' => [
                'assets' => [$fooIri],
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Renditions sharing the same file are distinct entries of the public
     * share, identified by the rendition (not the file).
     */
    public function testPublicShareListsRenditionsById(): void
    {
        $client = static::createClient();
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();

        $file = new File();
        $file->setWorkspace($workspace);
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath('test/'.uniqid().'.jpg');
        $file->setType('image/jpeg');
        $file->setSize(1234);
        $em->persist($file);

        $asset = $this->createAsset([
            'ownerId' => KeycloakClientTestMock::ADMIN_UID,
            'no_flush' => true,
        ]);
        $asset->setSource($file);

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName('share-test');
        $policy->setPublic(true);
        $policy->setEditable(false);
        $em->persist($policy);

        $renditionIds = [];
        foreach (['preview', 'original'] as $name) {
            $definition = new RenditionDefinition();
            $definition->setWorkspace($workspace);
            $definition->setPolicy($policy);
            $definition->setName($name);
            $definition->setTarget(AssetTypeEnum::Both);
            $definition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
            $em->persist($definition);

            $rendition = new AssetRendition();
            $rendition->setAsset($asset);
            $rendition->setDefinition($definition);
            $rendition->setFile($file);
            $em->persist($rendition);
            $renditionIds[$name] = $rendition->getId();
        }
        $em->flush();

        $response = $client->request('POST', '/shares', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'assets' => ['/assets/'.$asset->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $response = $client->request('GET', sprintf('/shares/%s/public?token=%s', $data['id'], $data['token']));
        $this->assertResponseIsSuccessful();
        $urls = $response->toArray()['alternateUrls'];

        $byName = array_column($urls, null, 'name');
        $this->assertCount(2, $byName);
        foreach ($renditionIds as $name => $id) {
            $this->assertSame($id, $byName[$name]['id']);
            $this->assertSame($asset->getId(), $byName[$name]['assetId']);
            $this->assertSame('image/jpeg', $byName[$name]['type']);
            $this->assertSame(1234, $byName[$name]['size']);
            $this->assertStringContainsString('token='.$data['token'], $byName[$name]['url']);
        }
    }
}
