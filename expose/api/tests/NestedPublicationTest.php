<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Security\RemoteAuthenticatorClientTestMock;

class NestedPublicationTest extends AbstractTestCase
{
    public function testCreateNestedPublicationOK(): void
    {
        $id = $this->createPublication([
            'owner_id' => '123',
        ]);
        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'POST',
            '/publications',
            [
            'parentId' => $id,
            'title' => 'Sub Foo',
            'layout' => 'download',
        ]
        );
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertEquals('Sub Foo', $json['title']);
        $this->assertRegExp(
            '#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#',
            $json['id']
        );
        $this->assertEquals('123', $json['ownerId']);

        $this->assertArrayHasKey('parents', $json);
        $this->assertArrayHasKey('0', $json['parents']);
        $this->assertEquals($id, $json['parents'][0]['id']);
        $this->assertArrayHasKey('title', $json['parents'][0]);
        $this->assertEquals('123', $json['parents'][0]['ownerId']);
    }

    public function testGetNestedPublication(): void
    {
        $parentId = $this->createPublication();
        $childId = $this->createPublication(['parent_id' => $parentId]);

        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'GET',
            '/publications/'.$parentId
        );
        $json = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals($parentId, $json['id']);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('children', $json);
        $this->assertArrayHasKey('0', $json['children']);
        $this->assertEquals($childId, $json['children'][0]['id']);
        $this->assertArrayHasKey('title', $json['children'][0]);

        // Test child
        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'GET',
            '/publications/'.$childId
        );
        $json = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals($childId, $json['id']);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('children', $json);
        $this->assertArrayHasKey('0', $json['parents']);
        $this->assertEquals($parentId, $json['parents'][0]['id']);
        $this->assertArrayHasKey('title', $json['parents'][0]);
    }

    public function testGetPublicationWithDisabledChild(): void
    {
        $parentId = $this->createPublication();
        $this->createPublication([
            'parent_id' => $parentId,
            'enabled' => false,
        ]);

        $response = $this->request(null, 'GET', '/publications/'.$parentId);
        $json = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals($parentId, $json['id']);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('children', $json);
        $this->assertEmpty($json['children']);
    }

    public function testDeleteWontRemoveNonOrphanChild(): void
    {
        $tree = [
            'p1' => [
                'pA' => [],
            ],
            'p2' => [
                'pA' => [],
                'pB' => [],
            ],
            'pB' => [],
        ];
        $ids = [];
        $this->createTree($tree, $ids);

        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/publications/'.$ids['p1']
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertPublicationDoesNotExist($ids['p1']);
        $this->assertPublicationExists($ids['pA']);
        $this->assertPublicationExists($ids['pB']);
        $this->assertPublicationExists($ids['p2']);
    }

    public function testDeleteWontRemoveAChildWhichIsAlsoRoot(): void
    {
        $tree = [
            'p1' => [
                'pA' => [],
                'pB' => [],
            ],
            'pA' => [],
        ];
        $ids = [];
        $this->createTree($tree, $ids);

        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/publications/'.$ids['p1']
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->assertPublicationDoesNotExist($ids['p1']);
        $this->assertPublicationExists($ids['pA']);
        $this->assertPublicationDoesNotExist($ids['pB']);
    }

    public function testDeleteWillRemoveOrphanChild(): void
    {
        $tree = [
            'p1' => [
                'pA' => [],
            ],
            'p2' => [
                'pB' => [],
            ],
        ];
        $ids = [];
        $this->createTree($tree, $ids);

        $response = $this->request(
            RemoteAuthenticatorClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/publications/'.$ids['p1']
        );
        $this->assertPublicationDoesNotExist($ids['p1']);
        $this->assertPublicationDoesNotExist($ids['pA']);
        $this->assertPublicationExists($ids['p2']);
        $this->assertPublicationExists($ids['pB']);
    }

    private function createTree(array $tree, array &$ids, ?string $parentName = null): void
    {
        foreach ($tree as $pubName => $children) {
            if ($parentName) {
                if (!isset($ids[$pubName])) {
                    $ids[$pubName] = $this->createPublication(['parent_id' => $ids[$parentName]]);
                } elseif ($parentName) {
                    $this->addPublicationChild($ids[$parentName], $ids[$pubName]);
                }
            } else {
                $ids[$pubName] = $this->createPublication();
            }

            $this->createTree($children, $ids, $pubName);
        }
    }
}
