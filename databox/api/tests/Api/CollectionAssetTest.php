<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Tests\AbstractSearchTestCase;

class CollectionAssetTest extends AbstractSearchTestCase
{
    public function testPostCollectionAssets(): void
    {
        self::enableFixtures();

        $assetIri = $this->findIriBy(Asset::class, ['key' => 'foo']);
        $collectionIri = $this->findIriBy(Collection::class, ['title' => 'Collection #1']);

        static::createClient()->request('POST', '/collection-assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'collection' => $collectionIri,
                'asset' => $assetIri,
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $response = static::createClient()->request('POST', '/collection-assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'collection' => $collectionIri,
                'asset' => $assetIri,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $id = $data['id'];

        static::createClient()->request('DELETE', '/collection-assets/'.$id, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);
        static::createClient()->request('DELETE', '/collection-assets/'.$id, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
        ]);
        $this->assertResponseIsSuccessful();
    }
}
