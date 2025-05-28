<?php

declare(strict_types=1);

namespace Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\AttributeList\AttributeList;
use App\Entity\AttributeList\AttributeListItem;
use App\Tests\AbstractDataboxTestCase;

class AttributeListTest extends AbstractDataboxTestCase
{
    public function testAttributeListCrud(): void
    {
        self::enableFixtures();
        $client = static::createClient();

        $response = $client->request('POST', '/attribute-lists', [
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
            '@type' => 'attribute-list',
            'title' => 'Foo',
            'public' => false,
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        $client->request('PUT', '/attribute-lists/'.$id, [
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
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        static::getEntityManager()->clear();

        $client->request('POST', '/attribute-lists/default/items', [
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
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        $def1 = $this->createAttributeDefinition([
            'name' => 'Attribute 1',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);
        $def2 = $this->createAttributeDefinition([
            'name' => 'Attribute 2',
            'workspace' => $this->getOrCreateDefaultWorkspace(),
        ]);

        $client->request('POST', '/attribute-lists/default/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => AttributeListItem::TYPE_ATTR_DEF,
                        'definition' => $def1->getId(),
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                    'definition' => $def1->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        $client->request('POST', '/attribute-lists/'.$id.'/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => AttributeListItem::TYPE_ATTR_DEF,
                        'definition' => $def2->getId(),
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'definition' => $def1->getId(),
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                ],
                [
                    'definition' => $def2->getId(),
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        $response = $client->request('POST', '/attribute-lists/'.$id.'/items', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'items' => [
                    [
                        'type' => AttributeListItem::TYPE_ATTR_DEF,
                        'definition' => $def2->getId(),
                    ],
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                    'definition' => $def1->getId(),
                ],
                [
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                    'definition' => $def2->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);
        $data = $response->toArray();

        $client->request('POST', '/attribute-lists/'.$id.'/remove', [
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
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'items' => [
                [
                    'type' => AttributeListItem::TYPE_ATTR_DEF,
                    'definition' => $def2->getId(),
                ],
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);
    }
}
