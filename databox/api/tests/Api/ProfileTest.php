<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Profile\Profile;
use App\Entity\Profile\ProfileItem;
use App\Tests\AbstractDataboxTestCase;

class ProfileTest extends AbstractDataboxTestCase
{
    public function testProfileCrud(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $response = $client->request('POST', '/profiles', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Foo',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@type' => 'profile',
            'title' => 'Foo',
            'public' => false,
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);

        $client->request('PUT', '/profiles/'.$id, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'title' => 'Foo renamed',
                'description' => 'Foo description',
                'public' => true,
            ],
        ]);
        $this->assertJsonContains([
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);

        static::getEntityManager()->clear();

        $client->request('POST', '/profiles/default/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [],
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);

        $def1 = $this->createAttributeDefinition([
            'name' => 'Attribute 1',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);
        $def2 = $this->createAttributeDefinition([
            'name' => 'Attribute 2',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);

        $client->request('POST', '/profiles/default/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => ProfileItem::TYPE_ATTR_DEF,
                        'definition' => $def1->getId(),
                        'section' => ProfileItem::SECTION_ATTRIBUTES,
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                    'definition' => $def1->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);

        $client->request('POST', '/profiles/'.$id.'/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => ProfileItem::TYPE_ATTR_DEF,
                        'definition' => $def2->getId(),
                        'section' => ProfileItem::SECTION_ATTRIBUTES,
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'definition' => $def1->getId(),
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                ],
                [
                    'definition' => $def2->getId(),
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);

        $response = $client->request('POST', '/profiles/'.$id.'/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => ProfileItem::TYPE_ATTR_DEF,
                        'definition' => $def2->getId(),
                        'section' => ProfileItem::SECTION_ATTRIBUTES,
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                    'definition' => $def1->getId(),
                ],
                [
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                    'definition' => $def2->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);
        $data = $response->toArray();

        $client->request('POST', '/profiles/'.$id.'/remove', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [$data['items'][0]['id']],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'profile',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => ProfileItem::TYPE_ATTR_DEF,
                    'definition' => $def2->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(Profile::class);
    }
}
