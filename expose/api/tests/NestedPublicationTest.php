<?php

declare(strict_types=1);

namespace App\Tests;


use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class NestedPublicationTest extends AbstractExposeTestCase
{
    public function testCreateNestedPublicationOK(): void
    {
        $id = $this->createPublication([
            'ownerId' => '123',
        ]);
        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'POST',
            '/publications',
            [
            'parentId' => $id,
            'title' => 'Sub Foo',
            'config' => [
                'layout' => 'download',
            ],
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

        $this->assertArrayHasKey('parent', $json);
        $this->assertEquals($id, $json['parent']['id']);
        $this->assertArrayHasKey('title', $json['parent']);
        $this->assertEquals('123', $json['parent']['ownerId']);
    }

    public function testGetNestedPublication(): void
    {
        $parentId = $this->createPublication();
        $childId = $this->createPublication(['parent_id' => $parentId]);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
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
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'GET',
            '/publications/'.$childId
        );
        $json = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals($childId, $json['id']);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('children', $json);
        $this->assertArrayHasKey('id', $json['parent']);
        $this->assertArrayHasKey('title', $json['parent']);
        $this->assertEquals($parentId, $json['parent']['id']);
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

    public function testDeleteWillRemoveChildren(): void
    {
        $tree = [
            'p1' => [
                'pA' => [],
                'pB' => [],
            ],
            'p2' => [
                'pC' => [],
            ],
        ];
        $ids = [];
        $this->createTree($tree, [], $ids);

        $response = $this->request(
            AuthServiceClientTestMock::ADMIN_TOKEN,
            'DELETE',
            '/publications/'.$ids['p1']
        );
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertPublicationDoesNotExist($ids['p1']);
        $this->assertPublicationDoesNotExist($ids['pA']);
        $this->assertPublicationDoesNotExist($ids['pB']);
        $this->assertPublicationExists($ids['p2']);
        $this->assertPublicationExists($ids['pC']);
    }

    public function testListPublicationsWillDisplayOnlyRoot(): void
    {
        $tree = [
            'p1' => [
                'pA' => [],
                'pB' => [],
            ],
            'p2' => [
                'pC' => [],
            ],
        ];
        $ids = [];
        $this->createTree($tree, [], $ids);

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications', []);
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertEquals(2, count($json));
        $this->assertEquals('p1', $json[0]['title']);
        $this->assertEquals('p2', $json[1]['title']);
    }

    private function createTree(array $tree, array $options, array &$ids, ?string $parentName = null): void
    {
        foreach ($tree as $pubName => $children) {
            $options['title'] = $pubName;
            if ($parentName) {
                $ids[$pubName] = $this->createPublication(array_merge($options, ['parent_id' => $ids[$parentName]]));
            } else {
                $ids[$pubName] = $this->createPublication($options);
            }

            $this->createTree($children, $options, $ids, $pubName);
        }
    }
}
