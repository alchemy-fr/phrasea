<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Collection;

use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface as Privacy;
use App\Tests\Functional\AbstractSearchTestCase;

/**
 * GET /collections/{id}/es-document and POST /collections/{id}/es-document-sync
 * (reserved to ROLE_TECH, which the admin is granted).
 */
final class CollectionEsDocumentTest extends AbstractSearchTestCase
{
    use CollectionTestTrait;

    public function testGetDocument(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $root = $this->createCollection(['workspace' => $workspace, 'name' => 'Root']);
        $child = $this->createCollection(['workspace' => $workspace, 'name' => 'Child', 'parent' => $root]);
        self::populateSearchIndices();

        $response = $this->request('GET', '/collections/'.$child->getId().'/es-document', self::ADMIN);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertTrue($data['synced']);
        $this->assertSame($child->getId(), $data['data']['_id']);
        $source = $data['data']['_source'];
        $this->assertSame('Child', $source['name']);
        $this->assertSame(1, $source['pathDepth']);
        $this->assertSame('/'.$root->getId().'/'.$child->getId(), $source['absolutePath']);
        $this->assertSame($workspace->getId(), $source['workspaceId']);
        $this->assertFalse($source['deleted']);
    }

    public function testDocumentIsOutOfSyncWhenChangedBehindTheIndexer(): void
    {
        $collection = $this->createIndexedCollectionThenRenameItInDatabase();

        $response = $this->request('GET', '/collections/'.$collection->getId().'/es-document', self::ADMIN);
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertFalse($data['synced']);
        $this->assertSame('Before', $data['data']['_source']['name']);
    }

    public function testSyncReindexesTheCollection(): void
    {
        $this->markTestIncomplete('BUG: the es-document-sync operation (src/Entity/Core/Collection.php:150) keeps the default CollectionInput: the request body goes through CollectionInputTransformer, which sets the name of the collection to null (not flushed) before ItemElasticsearchDocumentSyncProcessor indexes it, so the ES document gets "name": null; an unknown ID also yields 400 "Missing workspace" instead of 404 (input: false / deserialize: false missing)');

        $collection = $this->createIndexedCollectionThenRenameItInDatabase();

        $response = $this->request('POST', '/collections/'.$collection->getId().'/es-document-sync', self::ADMIN, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('', $response->getContent());
        self::waitForESIndex('collection');

        $response = $this->request('GET', '/collections/'.$collection->getId().'/es-document', self::ADMIN);
        $data = $response->toArray();
        $this->assertSame('After', $data['data']['_source']['name']);
        $this->assertTrue($data['synced']);
        $this->assertSame('After', $this->findCollection($collection->getId())->getName());

        $this->request('POST', '/collections/00000000-0000-4000-8000-000000000000/es-document-sync', self::ADMIN, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSyncDoesNotPersistTheRequestBody(): void
    {
        $collection = $this->createIndexedCollectionThenRenameItInDatabase();

        $this->request('POST', '/collections/'.$collection->getId().'/es-document-sync', self::ADMIN, [
            'json' => ['name' => 'Injected', 'privacy' => Privacy::PUBLIC],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $collection = $this->findCollection($collection->getId());
        $this->assertSame('After', $collection->getName());
        $this->assertSame(Privacy::SECRET, $collection->getPrivacy());
    }

    public function testReservedToTechnicalUsers(): void
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'C', 'ownerId' => self::USER]);
        $this->setCollectionPrivacy($collection, Privacy::PUBLIC);

        // Even the owner of the collection and of the workspace
        $this->request('GET', '/collections/'.$collection->getId().'/es-document', self::USER);
        $this->assertResponseStatusCodeSame(403);
        $this->request('POST', '/collections/'.$collection->getId().'/es-document-sync', self::USER, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->request('GET', '/collections/'.$collection->getId().'/es-document', self::ANONYMOUS);
        $this->assertResponseStatusCodeSame(401);
        $this->request('POST', '/collections/'.$collection->getId().'/es-document-sync', self::ANONYMOUS, [
            'json' => [],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUnknownCollection(): void
    {
        $this->request('GET', '/collections/00000000-0000-4000-8000-000000000000/es-document', self::ADMIN);
        $this->assertResponseStatusCodeSame(404);
    }

    private function createIndexedCollectionThenRenameItInDatabase(): Collection
    {
        $workspace = $this->createTestWorkspace(['ownerId' => self::USER]);
        $collection = $this->createCollection(['workspace' => $workspace, 'name' => 'Before']);
        self::populateSearchIndices();

        // Changed behind the indexer's back
        self::getEntityManager()->getConnection()->executeStatement(
            'UPDATE collection SET name = :name WHERE id = :id',
            ['name' => 'After', 'id' => $collection->getId()],
        );
        self::getEntityManager()->clear();

        return $collection;
    }
}
