<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Tests\AbstractSearchTestCase;

class BasketTest extends AbstractSearchTestCase
{
    public function testBasketIsNotVisibleToOtherUsers(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $response = $client->request('POST', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
            'json' => [
                'name' => 'My private basket',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('basket');

        // Owner sees it
        $response = $client->request('GET', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([$id], array_column($response->toArray()['hydra:member'], 'id'));

        // Other user does not see it in the list
        $response = $client->request('GET', '/baskets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::OTHER_USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame([], array_column($response->toArray()['hydra:member'], 'id'));

        // Other user cannot read it
        $client->request('GET', '/baskets/'.$id, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::OTHER_USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Other user cannot list its assets
        $client->request('GET', '/baskets/'.$id.'/assets', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::OTHER_USER_UID),
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        // Anonymous cannot read it
        $client->request('GET', '/baskets/'.$id);
        $this->assertResponseStatusCodeSame(401);
    }
}
