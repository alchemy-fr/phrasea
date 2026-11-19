<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\IndexPersister;
use Alchemy\ESBundle\Indexer\IndexWriteJournalInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use PHPUnit\Framework\TestCase;

class IndexPersisterTest extends TestCase
{
    protected function setUp(): void
    {
        A::reset();
    }

    public function testWritesAreJournaledBeforeBeingSent(): void
    {
        $calls = [];
        $journal = $this->createMock(IndexWriteJournalInterface::class);
        $journal->method('recordDocuments')->willReturnCallback(function (string $indexName, array $ids) use (&$calls): void {
            $calls[] = ['journal', $indexName, $ids];
        });

        $persister = $this->createMock(ObjectPersisterInterface::class);
        $persister->method('replaceMany')->willReturnCallback(function (array $objects) use (&$calls): void {
            $calls[] = ['replaceMany', \count($objects)];
        });
        $persister->method('deleteManyByIdentifiers')->willReturnCallback(function (array $ids) use (&$calls): void {
            $calls[] = ['deleteManyByIdentifiers', $ids];
        });
        $persister->method('deleteById')->willReturnCallback(function ($id) use (&$calls): void {
            $calls[] = ['deleteById', $id];
        });

        $indexPersister = new IndexPersister([A::class => ['a_index' => $persister]], writeJournal: $journal);

        $indexPersister->replaceMany(A::class, [new A([]), new A([])]);
        $indexPersister->deleteManyByIdentifiers(A::class, ['x', 'y']);
        $indexPersister->deleteById(A::class, 'z');

        $this->assertSame([
            ['journal', 'a_index', ['A1', 'A2']],
            ['replaceMany', 2],
            ['journal', 'a_index', ['x', 'y']],
            ['deleteManyByIdentifiers', ['x', 'y']],
            ['journal', 'a_index', ['z']],
            ['deleteById', 'z'],
        ], $calls);
    }

    public function testPersistersWithoutIndexNameAreNotJournaled(): void
    {
        $journal = $this->createMock(IndexWriteJournalInterface::class);
        $journal->expects($this->never())->method('recordDocuments');

        $persister = $this->createMock(ObjectPersisterInterface::class);
        $persister->expects($this->once())->method('deleteManyByIdentifiers');

        (new IndexPersister([A::class => [$persister]], writeJournal: $journal))->deleteManyByIdentifiers(A::class, ['x']);
    }
}
