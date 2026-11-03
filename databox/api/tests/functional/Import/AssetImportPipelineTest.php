<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use App\Consumer\Handler\File\ImportFile;
use App\Consumer\Handler\Import\DispatchAssetImport;
use App\Consumer\Handler\Import\ImportAssetEntry;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetImport;
use App\Entity\Core\AssetImportError;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Model\AssetImportStatusEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

/**
 * From the instructions file to the assets: the file is read by one message, which sends
 * one message per entry; each entry creates its asset as POST /assets would.
 */
final class AssetImportPipelineTest extends AbstractDataboxTestCase
{
    use AssetImportTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interceptQueues();
    }

    public function testNdjsonEntriesCreateAssetsWithImportedFilesAndSubstitutedRenditions(): void
    {
        [$workspace, $defaults] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $policy = $this->createAttributePolicy(['workspace' => $workspace, 'name' => 'Import']);
        $keywords = $this->createAttributeDefinition(['workspace' => $workspace, 'policy' => $policy, 'name' => 'Keywords', 'slug' => 'keywords', 'multiple' => true]);
        $description = $this->createAttributeDefinition(['workspace' => $workspace, 'policy' => $policy, 'name' => 'Description', 'slug' => 'description', 'translatable' => true]);

        $response = $this->postImport($workspace, self::ndjson([
            [
                'key' => 'ext-1',
                'externalId' => 'ext-1',
                'name' => 'Sunset',
                'sourceUrl' => 'https://cdn.example.com/a.jpg',
                'sourceName' => 'a.jpg',
                'previewUrl' => 'https://cdn.example.com/a-preview.jpg',
                'thumbnailUrl' => 'https://cdn.example.com/a-thumb.jpg',
                'attributes' => ['keywords' => ['sea', 'sky'], 'description:fr' => 'Coucher de soleil'],
                'unknownField' => 'ignored',
            ],
            '',
            ['key' => 'ext-2', 'name' => 'Only a name'],
            ['key' => 'ext-3', 'tags' => []],
        ]), ['originalName' => 'export.ndjson']);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('pending', $data['status']);
        $this->assertSame('ndjson', $data['format']);
        $this->assertSame('export.ndjson', $data['originalName']);
        $this->assertSame(self::OWNER, $data['owner']['id']);
        $importId = $data['id'];
        $this->assertCount(1, $this->getQueuedMessages($this->taskQueue, DispatchAssetImport::class));

        // Reading the file
        $this->consumeImportMessages(DispatchAssetImport::class);
        $entries = $this->getQueuedMessages($this->taskQueue, ImportAssetEntry::class);
        $this->assertSame([1, 3], array_map(static fn (ImportAssetEntry $entry): int => $entry->line, $entries));
        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Processing, $import->getStatus());
        $this->assertSame(3, $import->getTotal());
        $this->assertSame(1, $import->getFailed());

        // Importing the entries
        $this->consumeImportMessages();
        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Completed, $import->getStatus());
        $this->assertSame([2, 0, 1], [$import->getCreated(), $import->getSkipped(), $import->getFailed()]);
        $this->assertNotNull($import->getEndedAt());

        $em = self::getEntityManager();
        $asset = $em->getRepository(Asset::class)->findOneBy(['workspace' => $workspace->getId(), 'key' => 'ext-1']);
        $this->assertSame('ext-1', $asset->getExternalId());
        $this->assertSame(self::OWNER, $asset->getOwnerId());
        $this->assertTrue($asset->getSource()->isImportPending());
        $this->assertSame('https://cdn.example.com/a.jpg', $asset->getSource()->getPath());
        $this->assertSame('a.jpg', $asset->getSource()->getOriginalName());

        $renditions = [];
        foreach ($asset->getRenditions() as $rendition) {
            $renditions[$rendition->getDefinition()->getId()] = $rendition;
        }
        $importedFileIds = array_map(static fn (ImportFile $message): string => $message->getFileId(), $this->getQueuedMessages($this->p2Queue, ImportFile::class));
        foreach ([
            'preview' => 'https://cdn.example.com/a-preview.jpg',
            'thumbnail' => 'https://cdn.example.com/a-thumb.jpg',
        ] as $definitionKey => $url) {
            $rendition = $renditions[$defaults->renditionDefinitions[$definitionKey]->getId()];
            $this->assertTrue($rendition->isSubstituted(), $definitionKey);
            $this->assertSame($url, $rendition->getFile()->getPath());
            $this->assertTrue($rendition->getFile()->isImportPending());
            $this->assertContains($rendition->getFile()->getId(), $importedFileIds);
        }

        $values = [];
        foreach ($em->getRepository(Attribute::class)->findBy(['asset' => $asset->getId()]) as $attribute) {
            $values[$attribute->getDefinition()->getSlug()][] = [$attribute->getValue(), $attribute->getLocale()];
        }
        $this->assertEqualsCanonicalizing([['sea', null], ['sky', null]], $values[$keywords->getSlug()]);
        $this->assertSame([['Coucher de soleil', 'fr']], $values[$description->getSlug()]);

        $nameOnly = $em->getRepository(Asset::class)->findOneBy(['workspace' => $workspace->getId(), 'key' => 'ext-2']);
        $this->assertNull($nameOnly->getSource());

        $errors = $em->getRepository(AssetImportError::class)->findBy(['import' => $importId]);
        $this->assertCount(1, $errors);
        $this->assertSame(4, $errors[0]->getLine());
        $this->assertSame('ext-3', $errors[0]->getKey());
        $this->assertStringContainsString('must have a "sourceUrl"', $errors[0]->getMessage());
    }

    public function testExistingKeysAreSkippedSoThatAReimportIsIdempotent(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $existing = $this->createAsset(['workspace' => $workspace, 'ownerId' => self::OWNER, 'no_flush' => true]);
        $existing->setKey('ext-1');
        self::getEntityManager()->flush();

        $file = self::ndjson([
            ['key' => 'ext-1', 'name' => 'Already there'],
            ['key' => 'ext-2', 'name' => 'New'],
            ['key' => 'ext-2', 'name' => 'Same key in the same file'],
        ]);

        $firstId = $this->postImport($workspace, $file)->toArray()['id'];
        $this->consumeImportMessages();
        $first = $this->findImport($firstId);
        $this->assertSame([1, 2], [$first->getCreated(), $first->getSkipped()]);

        $secondId = $this->postImport($workspace, $file)->toArray()['id'];
        $this->consumeImportMessages();
        $second = $this->findImport($secondId);
        $this->assertSame(AssetImportStatusEnum::Completed, $second->getStatus());
        $this->assertSame([0, 3, 0], [$second->getCreated(), $second->getSkipped(), $second->getFailed()]);

        $this->assertCount(2, self::getEntityManager()->getRepository(Asset::class)->findBy(['workspace' => $workspace->getId()]));
    }

    public function testPathsCreateTheirCollectionsOnceUnderTheDestination(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        $destination = $this->createCollection(['workspace' => $workspace, 'ownerId' => self::OWNER, 'name' => 'Destination']);

        $importId = $this->postImport($workspace, self::ndjson([
            ['key' => 'a', 'name' => 'a', 'path' => 'Sport/Football'],
            ['key' => 'b', 'name' => 'b', 'path' => 'Sport / Football'],
            ['key' => 'c', 'name' => 'c', 'path' => 'Sport/Tennis'],
            ['key' => 'd', 'name' => 'd', 'collection' => $destination->getId(), 'path' => 'Other'],
            ['key' => 'e', 'name' => 'e', 'collection' => '/collections/'.$destination->getId()],
        ]), ['collection' => '/collections/'.$destination->getId()])->toArray()['id'];
        $this->consumeImportMessages();

        $import = $this->findImport($importId);
        $this->assertSame([4, 1], [$import->getCreated(), $import->getFailed()]);

        $sport = $this->findCollections($workspace, 'Sport');
        $this->assertCount(1, $sport);
        $this->assertSame($destination->getId(), $sport[0]->getParent()->getId());
        $football = $this->findCollections($workspace, 'Football');
        $this->assertCount(1, $football);
        $this->assertSame($sport[0]->getId(), $football[0]->getParent()->getId());
        $this->assertCount(1, $this->findCollections($workspace, 'Tennis'));
        $this->assertCount(0, $this->findCollections($workspace, 'Other'));

        $em = self::getEntityManager();
        foreach (['a', 'b'] as $name) {
            $asset = $this->findAssetByKey($workspace, $name);
            $this->assertSame($football[0]->getId(), $asset->getReferenceCollectionId(), $name);
        }
        $this->assertSame($destination->getId(), $this->findAssetByKey($workspace, 'e')->getReferenceCollectionId());

        $errors = $em->getRepository(AssetImportError::class)->findBy(['import' => $importId]);
        $this->assertSame(4, $errors[0]->getLine());
        $this->assertStringContainsString('"collection" and "path" cannot be used together', $errors[0]->getMessage());
    }

    public function testReferencesUnknownToTheWorkspaceFailTheirEntry(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);
        [$otherWorkspace] = $this->createWorkspaceWithDefaults('ws-other', self::OWNER);
        $otherCollection = $this->createCollection(['workspace' => $otherWorkspace, 'ownerId' => self::OWNER]);

        $importId = $this->postImport($workspace, self::ndjson([
            ['name' => 'a', 'tags' => ['Unknown tag']],
            ['name' => 'b', 'renditions' => ['unknown' => 'https://cdn.example.com/b.jpg']],
            ['name' => 'c', 'collection' => $otherCollection->getId()],
            ['name' => 'd', 'privacy' => 42],
            ['name' => 'e', 'renditions' => ['Preview' => 'https://cdn.example.com/e.jpg']],
            '{"name": "f"',
        ]))->toArray()['id'];
        $this->consumeImportMessages();

        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Completed, $import->getStatus());
        $this->assertSame([1, 5], [$import->getCreated(), $import->getFailed()]);

        $messages = [];
        foreach (self::getEntityManager()->getRepository(AssetImportError::class)->findBy(['import' => $importId], ['line' => 'ASC']) as $error) {
            $messages[$error->getLine()] = $error->getMessage();
        }
        $this->assertSame([1, 2, 3, 4, 6], array_keys($messages));
        $this->assertStringContainsString('Tag "Unknown tag" not found', $messages[1]);
        $this->assertStringContainsString('Rendition definition "unknown" not found', $messages[2]);
        $this->assertStringContainsString('not found in the workspace', $messages[3]);
        $this->assertStringContainsString('Invalid privacy', $messages[4]);
        $this->assertStringStartsWith('Invalid JSON', $messages[6]);
    }

    public function testAnInvalidJsonFileFailsTheImport(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $importId = $this->postImport($workspace, '[{"name": "a"}', ['format' => AssetImport::FORMAT_JSON])->toArray()['id'];
        $this->consumeImportMessages();

        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Failed, $import->getStatus());
        $this->assertStringStartsWith('Invalid JSON', $import->getError());
        $this->assertNotNull($import->getEndedAt());
        $this->assertSame([], $this->getQueuedMessages($this->taskQueue, ImportAssetEntry::class));

        $data = $this->apiRequest('GET', '/asset-imports/'.$importId, self::OWNER)->toArray();
        $this->assertSame('failed', $data['status']);
        $this->assertStringStartsWith('Invalid JSON', $data['error']);
    }

    public function testJsonListImport(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $importId = $this->postImport($workspace, json_encode(['assets' => [
            ['key' => 'a', 'name' => 'A'],
            ['key' => 'b', 'name' => 'B'],
        ]]), ['format' => AssetImport::FORMAT_JSON])->toArray()['id'];
        $this->consumeImportMessages();

        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Completed, $import->getStatus());
        $this->assertSame([2, 2], [$import->getTotal(), $import->getCreated()]);
    }

    public function testAnEmptyFileCompletesAtOnce(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $importId = $this->postImport($workspace, '[]', ['format' => AssetImport::FORMAT_JSON])->toArray()['id'];
        $this->consumeImportMessages();

        $import = $this->findImport($importId);
        $this->assertSame(AssetImportStatusEnum::Completed, $import->getStatus());
        $this->assertSame(0, $import->getTotal());
    }

    public function testTheEntriesOfADeletedImportAreDropped(): void
    {
        [$workspace] = $this->createWorkspaceWithDefaults('ws-import', self::OWNER);

        $importId = $this->postImport($workspace, self::ndjson([['name' => 'a']]))->toArray()['id'];
        $this->consumeImportMessages(DispatchAssetImport::class);
        $this->apiRequest('DELETE', '/asset-imports/'.$importId, self::OWNER);
        $this->assertResponseStatusCodeSame(204);

        $this->consumeImportMessages();
        $this->assertCount(0, self::getEntityManager()->getRepository(Asset::class)->findBy(['workspace' => $workspace->getId()]));
    }

    /**
     * @return Collection[]
     */
    private function findCollections(Workspace $workspace, string $name): array
    {
        return self::getEntityManager()->getRepository(Collection::class)->findBy([
            'workspace' => $workspace->getId(),
            'name' => $name,
        ]);
    }

    private function findAssetByKey(Workspace $workspace, string $key): Asset
    {
        return self::getEntityManager()->getRepository(Asset::class)->findOneBy(['workspace' => $workspace->getId(), 'key' => $key]);
    }
}
