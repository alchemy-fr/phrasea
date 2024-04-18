<?php

namespace Alchemy\ESBundle\Tests;

use Alchemy\ESBundle\Indexer\IndexPersister;
use Alchemy\ESBundle\Indexer\SearchIndexer;
use ColinODell\PsrTestLogger\TestLogger;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class SearchIndexerTest extends TestCase
{
    public function testIndexOK(): void
    {
        $as = [];
        $bs = [];
        foreach ([
                     new A([
                         new B()
                     ]),
                     new A([]),
                     new A([]),
                 ] as $a) {
            $as[$a->getId()] = $a;
            foreach ($a->getChildren() as $child) {
                $bs[$child->getId()] = $child;
            }
        }

        $store = [
            A::class => $as,
            B::class => $bs,
        ];

        $objects = [
            A::class => [
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), $as),
            ]
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        $this->assertIndexed($testLogger, A::class, ['A1', 'A2']);
        $this->assertIndexed($testLogger, A::class, ['A3']);
        $this->assertIndexed($testLogger, B::class, ['B1']);
    }

    public function testIndexManyObjectsOK(): void
    {
        $store = [
            A::class => [],
            B::class => [],
        ];

        $createB = function () use (&$store): B {
            $o = new B();
            return $store[B::class][$o->getId()] = $o;
        };
        $createA = function () use (&$store, $createB): A {
            $children = [];
            $items = range(1, random_int(1, 10));
            foreach ($items as $i) {
                $children[] = $createB();
            }
            $o = new A($children);

            return $store[A::class][$o->getId()] = $o;
        };

        for ($i = 0; $i < 10; $i++) {
            $createA();
        }

        $objects = [
            A::class => [
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), $store[A::class]),
            ]
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        $this->assertEquals(ceil((count($store[A::class]) + count($store[B::class])) / 2), count($testLogger->records));
    }

    private function assertIndexed(TestLogger $logger, string $class, array $ids): void
    {
        $expectedLog = sprintf(
            'ES index %s %s: ("%s")',
            $class,
            SearchIndexer::ACTION_UPSERT,
            implode('", "', $ids),
        );

        $this->assertTrue($logger->hasRecordThatContains($expectedLog), 'Expected '.$expectedLog);
    }

    /**
     * @return array{SearchIndexer, TestLogger}
     */
    private function createIndexer(array $store, iterable $dependenciesResolvers): array
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $queryBuilderMock = new QueryBuilderMock($em, $store);
        $em->method('createQueryBuilder')->willReturn($queryBuilderMock);
        $em->method('getConfiguration')->willReturn(new Configuration());
        $indexPersister = new IndexPersister([]);

        $testLogger = new TestLogger();

        $searchIndexer = new SearchIndexer(
            $bus,
            $em,
            $testLogger,
            $indexPersister,
            $dependenciesResolvers,
            true,
            1000,
            2,
            4,
            10
        );


        return [$searchIndexer, $testLogger];
    }
}
