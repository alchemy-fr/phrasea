<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Core\Asset;
use App\Tests\AbstractDataboxTestCase;

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
}
