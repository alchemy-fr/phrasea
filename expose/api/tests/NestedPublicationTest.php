<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\RemoteAuthBundle\Tests\Client\AuthServiceClientTestMock;

class NestedPublicationTest extends AbstractExposeTestCase
{
    public function testCreateNestedPublicationOK(): void
    {
        $id = $this->createPublication([
            'ownerId' => AuthServiceClientTestMock::ADMIN_UID,
        ])->getId();
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
        $this->assertMatchesUuid($json['id']);
        $this->assertEquals(AuthServiceClientTestMock::ADMIN_UID, $json['ownerId']);

        $this->assertArrayHasKey('parent', $json);
        $this->assertEquals($id, $json['parent']['id']);
        $this->assertArrayHasKey('title', $json['parent']);
        $this->assertEquals(AuthServiceClientTestMock::ADMIN_UID, $json['parent']['ownerId']);
    }

    public function testNestedPublicationIsCorrectlyNormalizedWithDifferentAcceptHeaders(): void
    {
        $parentId = $this->createPublication(['no_flush' => true])->getId();
        $childId = $this->createPublication(['parent_id' => $parentId])->getId();
        $this->clearEmBeforeApiCall();

        foreach ([
                     null,
                     '*/*',
                     'application/json',
                     'application/ld+json',
                 ] as $accept) {
            $response = $this->request(
                AuthServiceClientTestMock::ADMIN_TOKEN,
                'GET',
                '/publications/'.$childId,
                [],
                [],
                ['HTTP_ACCEPT' => $accept]
            );
            $json = json_decode($response->getContent(), true);

            $this->assertEquals(200, $response->getStatusCode());

            $this->assertArrayHasKey('parent', $json);
            $this->assertEquals($parentId, $json['parent']['id']);
            $this->assertArrayHasKey('id', $json);
            $this->assertArrayHasKey('title', $json);
            $this->assertEquals(true, $json['authorized']);
        }
    }

    public function testGetNestedOrderPublication(): void
    {
        $parentId = $this->createPublication([
            'title' => 'A',
            'no_flush' => true,
        ])->getId();
        $childId2 = $this->createPublication([
            'title' => 'A.B',
            'parent_id' => $parentId,
            'no_flush' => true,
        ])->getId();
        $childId = $this->createPublication([
            'title' => 'A.A',
            'parent_id' => $parentId,
        ])->getId();
        $this->clearEmBeforeApiCall();

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
        $this->assertCount(2, $json['children']);
        $this->assertEquals($childId, $json['children'][0]['id']);
        $this->assertArrayHasKey('title', $json['children'][0]);
        $this->assertEquals('A.A', $json['children'][0]['title']);
        $this->assertEquals($childId2, $json['children'][1]['id']);
        $this->assertArrayHasKey('title', $json['children'][1]);
        $this->assertEquals('A.B', $json['children'][1]['title']);

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
        $parentId = $this->createPublication()->getId();
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

        $response = $this->request(AuthServiceClientTestMock::ADMIN_TOKEN, 'GET', '/publications');
        $json = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $response->headers->get('Content-Type'));

        $this->assertCount(2, $json);
        $this->assertEquals('p1', $json[0]['title']);
        $this->assertEquals('p2', $json[1]['title']);
    }

    private function createTree(array $tree, array $options, array &$ids, string $parentName = null): void
    {
        foreach ($tree as $pubName => $children) {
            $options['title'] = $pubName;
            if ($parentName) {
                $ids[$pubName] = $this->createPublication(array_merge($options, ['parent_id' => $ids[$parentName]]))->getId();
            } else {
                $ids[$pubName] = $this->createPublication($options)->getId();
            }

            $this->createTree($children, $options, $ids, $pubName);
        }
    }
}
