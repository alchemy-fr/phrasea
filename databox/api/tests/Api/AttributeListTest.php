<?php

declare(strict_types=1);

namespace Api;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\AttributeList\AttributeList;
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

        $client->request('POST', '/attribute-lists/default/definitions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'definitions' => [],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'id' => $id,
            '@type' => 'attribute-list',
            'title' => 'Foo renamed',
            'description' => 'Foo description',
            'public' => true,
            'definitions' => [],
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

        $client->request('POST', '/attribute-lists/default/definitions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'definitions' => [
                    'id' => $def1->getId(),
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
            'definitions' => [
                $def1->getId(),
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);

        $client->request('POST', '/attribute-lists/'.$id.'/definitions', [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
            ],
            'json' => [
                'definitions' => [
                    $def2->getId(),
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
            'definitions' => [
                $def1->getId(),
                $def2->getId(),
            ],
        ]);
        $this->assertMatchesResourceItemJsonSchema(AttributeList::class);
    }
}
