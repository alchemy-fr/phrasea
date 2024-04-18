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
    protected function setUp(): void
    {
        A::reset();
        B::reset();
        C::reset();
    }

    public function testIndexOK(): void
    {
        $as = [];
        $bs = [];
        foreach ([
            new A([
                new B(),
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
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), array_values($as)),
            ],
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        $this->assertIndexed($testLogger, A::class, ['A1', 'A2']);
        $this->assertIndexed($testLogger, A::class, ['A3']);
        $this->assertIndexed($testLogger, B::class, ['B1 (A1)']);
    }

    public function testIndexWithProxyOK(): void
    {
        $as = [];
        $bs = [];
        $cs = [];
        foreach ([
            new A([
                new B(),
            ], true),
            new A([], true),
            new A([], true),
        ] as $a) {
            $as[$a->getId()] = $a;
            foreach ($a->getChildren() as $child) {
                $bs[$child->getId()] = $child;
                $next = $child->getNext();
                $cs[$next->getId()] = $next;
            }
        }

        $store = [
            A::class => $as,
            B::class => $bs,
            C::class => $cs,
        ];

        $objects = [
            A::class => [
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), array_values($as)),
            ],
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        //        dump($testLogger->records);
        $this->assertIndexed($testLogger, A::class, ['A1', 'A2']);
        $this->assertIndexed($testLogger, A::class, ['A3']);
        $this->assertIndexed($testLogger, B::class, ['B1']);
        $this->assertIndexed($testLogger, C::class, ['C1 (A1)']);
    }

    public function testIndexManyObjectsOK(): void
    {
        $store = [
            A::class => [],
            B::class => [],
        ];

        $createA = function () use (&$store): A {
            $children = [];
            $items = range(1, random_int(1, 10));
            foreach ($items as $i) {
                $b = new B();
                $children[] = $b;
            }

            $o = new A($children);

            foreach ($children as $child) {
                $store[B::class][$child->getId()] = $child;
            }

            return $store[A::class][$o->getId()] = $o;
        };

        for ($i = 0; $i < 3; ++$i) {
            $createA();
        }

        $objects = [
            A::class => [
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), array_values($store[A::class])),
            ],
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        $this->assertGreaterThanOrEqual(ceil((count($store[A::class]) + count($store[B::class])) / 2), count($testLogger->records));
    }

    public function testIndexManyObjectsWithProxyOK(): void
    {
        $store = [
            A::class => [],
            B::class => [],
            C::class => [],
        ];

        $createA = function () use (&$store): A {
            $children = [];
            $items = range(1, random_int(1, 100));
            foreach ($items as $i) {
                $b = new B();
                $children[] = $b;
            }

            $o = new A($children, true);

            foreach ($children as $child) {
                $store[B::class][$child->getId()] = $child;
                $next = $child->getNext();
                $store[C::class][$next->getId()] = $next;
            }

            return $store[A::class][$o->getId()] = $o;
        };

        for ($i = 0; $i < 100; ++$i) {
            $createA();
        }

        $objects = [
            A::class => [
                SearchIndexer::ACTION_UPSERT => array_map(fn (A $o) => $o->getId(), array_values($store[A::class])),
            ],
        ];

        [$searchIndexer, $testLogger] = $this->createIndexer($store, [
            new DummyDependencyResolver(),
        ]);

        $searchIndexer->index($objects, 0, []);
        $searchIndexer->flush();
        $this->assertGreaterThanOrEqual(ceil((count($store[A::class]) + count($store[B::class])) / 2), count($testLogger->records));
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
            2,
            2,
            4,
            2
        );

        return [$searchIndexer, $testLogger];
    }
}
