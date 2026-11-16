<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Command\ESPopulateUnlockCommand;
use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Exception\PopulateIndexNotReadyException;
use App\Elasticsearch\Exception\PopulateLockLostException;
use App\Elasticsearch\Exception\PopulateOwnerAliveException;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use App\Tests\Functional\DataboxTestTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Two populates of the same index must never run at the same time: the second
 * one is refused (and recorded as a failed pass) instead of deleting the pass
 * and the half-built physical index of the first one.
 */
class PopulateLockTest extends KernelTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;
    use AliasedIndexTestTrait;

    private const INDEX = 'attribute_entity';

    private EntityManagerInterface $em;

    private bool $aliased = false;

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->deletePasses();
    }

    protected function tearDown(): void
    {
        $this->deletePasses();
        if ($this->aliased) {
            static::getContainer()->get(PopulateLockManager::class)->forceRelease(self::INDEX, evenIfAlive: true);
            $this->restorePlainIndex(self::INDEX);
        }
        $this->defaultWorkspace = null;
        $this->defaultAttributePolicy = null;
        parent::tearDown();
    }

    public function testPopulateIsRefusedWhileTheIndexIsLocked(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        $otherProcess = $lockFactory->createLock(static::getContainer()->get(PopulateLockManager::class)->getLockName(self::INDEX), 60);
        $this->assertTrue($otherProcess->acquire(), 'the lock is free before the test');

        try {
            $this->expectException(PopulateAlreadyRunningException::class);
            $this->populate(catchExceptions: false);
        } finally {
            $otherProcess->release();

            $passes = $this->getPasses();
            $this->assertCount(1, $passes, 'the refused run is recorded');
            $this->assertNotNull($passes[0]->getEndedAt());
            $this->assertStringContainsString('already running', (string) $passes[0]->getError());
        }
    }

    public function testPopulateRunsAndReleasesTheLock(): void
    {
        $this->assertSame(0, $this->populate());

        $passes = $this->getPasses();
        $this->assertCount(1, $passes);
        $this->assertNull($passes[0]->getError());
        $this->assertNotNull($passes[0]->getEndedAt());

        // the lock is released at the end: a new populate can start right away
        $this->assertSame(0, $this->populate());
        $this->assertCount(2, $this->getPasses());
    }

    public function testAnInterruptedPassIsClosedByTheNextRun(): void
    {
        $interrupted = new PopulatePass();
        $interrupted->setIndexName(self::INDEX);
        $interrupted->setMapping([]);
        $interrupted->setDocumentCount(4);
        $interrupted->setProgress(1);
        $this->em->persist($interrupted);
        $this->em->flush();
        $this->em->clear();

        $this->assertSame(0, $this->populate());

        $passes = $this->getPasses();
        $this->assertCount(2, $passes, 'the stale pass is kept, not deleted');
        $stale = $this->em->find(PopulatePass::class, $interrupted->getId());
        $this->assertNotNull($stale->getEndedAt());
        $this->assertStringContainsString('Interrupted', (string) $stale->getError());
    }

    public function testForceReleaseRemovesTheLockAndClosesOpenPasses(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);

        $deadWorker = $lockFactory->createLock($manager->getLockName(self::INDEX), 600);
        $this->assertTrue($deadWorker->acquire());
        $open = new PopulatePass();
        $open->setIndexName(self::INDEX);
        $open->setMapping([]);
        $open->setDocumentCount(4);
        $open->setProgress(2);
        $this->em->persist($open);
        $this->em->flush();

        $this->assertTrue($manager->isLocked(self::INDEX));
        $this->assertSame([self::INDEX], $manager->getLockedIndices([self::INDEX, 'asset']));

        $result = $manager->forceRelease(self::INDEX);

        $this->assertSame(['lockReleased' => true, 'passesClosed' => 1], $result);
        $this->assertFalse($manager->isLocked(self::INDEX));
        $this->em->clear();
        $closed = $this->em->find(PopulatePass::class, $open->getId());
        $this->assertNotNull($closed->getEndedAt());
        $this->assertStringContainsString('lock released manually', (string) $closed->getError());

        // the index is usable again
        $this->assertSame(0, $this->populate());

        // releasing a free lock is harmless
        $this->assertSame(['lockReleased' => false, 'passesClosed' => 0], $manager->forceRelease(self::INDEX));
    }

    public function testLocksAreScopedByTheIndexPrefix(): void
    {
        $container = static::getContainer();
        /** @var LockFactory $lockFactory */
        $lockFactory = $container->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = $container->get(PopulateLockManager::class);
        // another stack sharing the same Redis server
        $otherStack = new PopulateLockManager(
            (new \ReflectionProperty(PopulateLockManager::class, 'redisDsn'))->getValue($manager),
            $this->em,
            'other_stack_',
        );

        $this->assertSame('es_populate_other_stack_'.self::INDEX, $otherStack->getLockName(self::INDEX));
        $this->assertNotSame($manager->getLockName(self::INDEX), $otherStack->getLockName(self::INDEX));

        $lock = $lockFactory->createLock($manager->getLockName(self::INDEX), 600);
        $this->assertTrue($lock->acquire());

        try {
            $this->assertTrue($manager->isLocked(self::INDEX));
            $this->assertFalse($otherStack->isLocked(self::INDEX), 'the other stack can populate the same logical index');
            $this->assertFalse($otherStack->forceRelease(self::INDEX)['lockReleased']);
            $this->assertTrue($manager->isLocked(self::INDEX), 'the other stack cannot release this lock');
        } finally {
            $lock->release();
        }
    }

    public function testPopulateProgressIsRecordedPageByPage(): void
    {
        $this->createEntities(3);

        $progress = [];
        $listener = function (PostInsertObjectsEvent $event) use (&$progress): void {
            $passes = $this->em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX]);
            $this->em->refresh($passes[0]);
            $progress[] = $passes[0]->getProgress();
        };

        $this->withPostInsertListener($listener, -2048, function (): void {
            $this->assertSame(0, $this->populate(maxPerPage: 1));
        });

        $this->assertSame([1, 2, 3], $progress);
        $passes = $this->getPasses();
        $this->assertCount(1, $passes);
        $this->assertNull($passes[0]->getError());
        $this->assertSame(3, $passes[0]->getProgress());
    }

    public function testDeletingThePassCancelsTheRunningPopulate(): void
    {
        $this->createEntities(3);

        $listener = function (): void {
            $this->deletePasses();
        };

        $this->withPostInsertListener($listener, 1024, function (): void {
            try {
                $this->populate(catchExceptions: false, maxPerPage: 1);
                $this->fail('the populate goes on after its pass has been deleted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('cancelled', $e->getMessage());
            }
        });

        $this->assertSame([], $this->getPasses(), 'no pass is recreated');
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $this->assertFalse($manager->isLocked(self::INDEX), 'the lock is released');

        // the index is usable again
        $this->assertSame(0, $this->populate());
    }

    public function testCloseDeadPassesOnlyClosesThePassesOfTheDeadRun(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);

        $deadRun = 'dead-run';
        $dead = $this->createOpenPass(new \DateTimeImmutable('-1 hour'), runId: $deadRun);
        $closedOfTheRun = $this->createOpenPass(new \DateTimeImmutable('-1 hour'), endedAt: new \DateTimeImmutable('-30 minutes'), runId: $deadRun);
        $deadOtherIndex = $this->createOpenPass(new \DateTimeImmutable('-1 hour'), indexName: 'collection', runId: $deadRun);
        // started from the admin after the dead run, while it was still running
        $otherRun = $this->createOpenPass(new \DateTimeImmutable(), indexName: 'asset_data_template', runId: 'other-run');
        $this->em->clear();

        $deadWorker = $lockFactory->createLock($manager->getLockName(self::INDEX), 600);
        $this->assertTrue($deadWorker->acquire());
        $otherWorker = $lockFactory->createLock($manager->getLockName('asset_data_template'), 600);
        $this->assertTrue($otherWorker->acquire());
        $manager->writeOwner('asset_data_template', ['runId' => 'other-run', 'passId' => $otherRun, 'host' => 'h', 'pid' => 1, 'index' => null], 600);

        try {
            $this->assertSame(1, $manager->closeDeadPasses($deadRun, self::INDEX, 'Process killed'));
            $this->assertFalse($manager->isLocked(self::INDEX), 'the lock of the dead run is released');

            $this->em->clear();
            $closed = $this->em->find(PopulatePass::class, $dead);
            $this->assertNotNull($closed->getEndedAt());
            $this->assertSame('Process killed', $closed->getError());
            $this->assertNull($this->em->find(PopulatePass::class, $closedOfTheRun)->getError(), 'a closed pass is left alone');
            $this->assertNull($this->em->find(PopulatePass::class, $deadOtherIndex)->getEndedAt(), 'another index is left alone');

            // without index: every open pass of the run, and only of the run
            $this->assertSame(1, $manager->closeDeadPasses($deadRun, null, 'Process killed'));
            $this->em->clear();
            $this->assertNotNull($this->em->find(PopulatePass::class, $deadOtherIndex)->getEndedAt());
            $this->assertNull($this->em->find(PopulatePass::class, $otherRun)->getEndedAt(), 'the pass of another run is left alone');
            $this->assertTrue($manager->isLocked('asset_data_template'), 'the lock of another run is kept');

            // even when its pass would be listed: the lock belongs to another run
            $this->assertSame(0, $manager->closeDeadPasses('other-run-not-owning', 'asset_data_template', 'Process killed'));
            $this->assertTrue($manager->isLocked('asset_data_template'));
        } finally {
            $deadWorker->release();
            $otherWorker->release();
            $manager->forceRelease('asset_data_template', evenIfAlive: true);
            $this->em->createQuery(sprintf('DELETE FROM %s p WHERE p.indexName IN (:i)', PopulatePass::class))
                ->setParameter('i', ['collection', 'asset_data_template'])
                ->execute();
        }
    }

    public function testPassesAreRecordedWithTheRunIdGivenByTheHandler(): void
    {
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);

        $this->assertSame(0, $this->populate());

        $passes = $this->getPasses();
        $this->assertCount(1, $passes);
        $this->assertSame($manager->getRunId(), $passes[0]->getRunId());
    }

    public function testTheOwnerIsRecordedWhileThePopulateRuns(): void
    {
        $this->withAlias();
        $this->createEntities(2);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);

        $owners = [];
        $listener = function () use ($manager, &$owners): void {
            $owner = $manager->getOwner(self::INDEX);
            $owners[] = [$owner, $manager->isAlive(self::INDEX), null !== $owner['index'] && $manager->isBuilding($owner['index'])];
        };

        $this->withPostInsertListener($listener, -2048, function (): void {
            $this->assertSame(0, $this->populate(maxPerPage: 1));
        });

        $this->assertCount(2, $owners);
        [$owner, $alive, $building] = $owners[0];
        $this->assertTrue($alive);
        $this->assertSame($manager->getRunId(), $owner['runId']);
        $this->assertSame($this->getPasses()[0]->getId(), $owner['passId']);
        $this->assertSame(getmypid(), $owner['pid']);
        $this->assertStringStartsWith($this->getAliasName(self::INDEX).'_', (string) $owner['index'], 'the physical index being built');
        $this->assertTrue($building);

        $this->assertNull($manager->getOwner(self::INDEX), 'the owner is forgotten at the end');
        $this->assertFalse($manager->isBuilding($owner['index']));
    }

    public function testForceReleaseIsRefusedWhileThePopulateIsAlive(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);

        $worker = $lockFactory->createLock($manager->getLockName(self::INDEX), 600);
        $this->assertTrue($worker->acquire());
        $manager->writeOwner(self::INDEX, ['runId' => 'run', 'passId' => 'pass-id', 'host' => 'worker-1', 'pid' => 42, 'index' => null], 600);

        try {
            try {
                $manager->forceRelease(self::INDEX);
                $this->fail('the lock of a live populate is released');
            } catch (PopulateOwnerAliveException $e) {
                $this->assertStringContainsString('host worker-1, pid 42', $e->getMessage());
            }
            $this->assertTrue($manager->isLocked(self::INDEX));

            // the command keeps it too
            $tester = new CommandTester(static::getContainer()->get(ESPopulateUnlockCommand::class));
            $this->assertSame(1, $tester->execute(['index' => self::INDEX, '--force' => true], ['interactive' => false]));
            $this->assertStringContainsString('--even-if-alive', $tester->getDisplay());
            $this->assertTrue($manager->isLocked(self::INDEX));

            $this->assertSame(0, $tester->execute(['index' => self::INDEX, '--force' => true, '--even-if-alive' => true], ['interactive' => false]));
            $this->assertFalse($manager->isLocked(self::INDEX));
            $this->assertNull($manager->getOwner(self::INDEX));
        } finally {
            $worker->release();
        }
    }

    public function testAPopulateWhoseLockIsReleasedStops(): void
    {
        $this->withAlias();
        $this->createEntities(3);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $aliasedBefore = $this->getAliasedIndex(self::INDEX);

        $released = false;
        $listener = function () use ($manager, &$released): void {
            if (!$released) {
                $released = true;
                $manager->forceRelease(self::INDEX, evenIfAlive: true);
            }
        };

        $this->withPostInsertListener($listener, 1024, function (): void {
            try {
                $this->populate(catchExceptions: false, maxPerPage: 1);
                $this->fail('the populate goes on without its lock');
            } catch (PopulateLockLostException $e) {
                $this->assertStringContainsString('lock was released', $e->getMessage());
            }
        });

        $passes = $this->getPasses();
        $this->assertCount(1, $passes);
        $this->assertNotNull($passes[0]->getEndedAt());
        $this->assertNotNull($passes[0]->getError());
        $this->assertSame($aliasedBefore, $this->getAliasedIndex(self::INDEX), 'the alias is not switched');
        $this->assertSame([], $this->getUnaliasedIndices(self::INDEX), 'the half-built index is removed');
    }

    public function testAnIndexBeingBuiltByAnotherPopulateIsNotRemovedAsAnOrphan(): void
    {
        $this->withAlias();
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $orphan = $this->getAliasName(self::INDEX).'_2000-01-01-000000';
        $building = $this->getAliasName(self::INDEX).'_2000-01-02-000000';
        $this->createPhysicalIndex($orphan);
        $this->createPhysicalIndex($building);
        // a populate whose lock was released by force, still running
        $manager->writeOwner('other_'.self::INDEX, ['runId' => 'other-run', 'passId' => null, 'host' => 'h', 'pid' => 1, 'index' => $building], 600);

        try {
            $this->assertSame(0, $this->populate());

            $unaliased = $this->getUnaliasedIndices(self::INDEX);
            $this->assertNotContains($orphan, $unaliased, 'an orphan index is removed');
            $this->assertContains($building, $unaliased, 'the index of a live populate is kept');
        } finally {
            $manager->releaseOwner('other_'.self::INDEX, 'other-run', $building);
            $this->getEsClient()->indices()->delete(['index' => $building, 'ignore_unavailable' => true]);
        }
    }

    public function testAnIndexDeletedWhileBeingPopulatedIsNotRecreated(): void
    {
        $this->withAlias();
        $this->createEntities(3);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $aliasedBefore = $this->getAliasedIndex(self::INDEX);

        $deleted = false;
        $listener = function () use ($manager, &$deleted): void {
            if (!$deleted) {
                $deleted = true;
                $this->getEsClient()->indices()->delete(['index' => $manager->getOwner(self::INDEX)['index']]);
            }
        };

        $this->withPostInsertListener($listener, -2048, function (): void {
            try {
                $this->populate(catchExceptions: false, maxPerPage: 1);
                $this->fail('the populate goes on without its index');
            } catch (PopulateIndexNotReadyException $e) {
                $this->assertStringContainsString('was deleted while being populated', $e->getMessage());
            }
        });

        $this->assertSame($aliasedBefore, $this->getAliasedIndex(self::INDEX), 'the alias is not switched');
        $this->assertSame([], $this->getUnaliasedIndices(self::INDEX), 'no index is created again');
        $this->assertFalse($manager->isLocked(self::INDEX));
        $this->assertNotNull($this->getPasses()[0]->getError());
    }

    public function testTheAliasIsNotSwitchedToAnIndexRecreatedWithADynamicMapping(): void
    {
        $this->withAlias();
        $this->createEntities(1);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $aliasedBefore = $this->getAliasedIndex(self::INDEX);

        // after the last page: a write recreates the deleted index, with a dynamic mapping
        $listener = function () use ($manager): void {
            $index = $manager->getOwner(self::INDEX)['index'];
            $this->getEsClient()->indices()->delete(['index' => $index]);
            $this->getEsClient()->index(['index' => $index, 'id' => 'x', 'body' => ['name' => 'x', 'workspaceId' => 'w']]);
        };

        $this->withPostInsertListener($listener, -2048, function (): void {
            try {
                $this->populate(catchExceptions: false);
                $this->fail('the alias is switched to the recreated index');
            } catch (PopulateIndexNotReadyException $e) {
                $this->assertStringContainsString('was not created by the populate', $e->getMessage());
            }
        });

        $this->assertSame($aliasedBefore, $this->getAliasedIndex(self::INDEX), 'the alias is not switched');
        $this->assertSame([], $this->getUnaliasedIndices(self::INDEX), 'the recreated index is removed');
        $this->assertFalse($manager->isLocked(self::INDEX));
    }

    public function testUnlockCommand(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        /** @var PopulateLockManager $manager */
        $manager = static::getContainer()->get(PopulateLockManager::class);
        $tester = new CommandTester(static::getContainer()->get(ESPopulateUnlockCommand::class));

        // nothing locked
        $this->assertSame(0, $tester->execute([], ['interactive' => false]));
        $this->assertStringContainsString('No populate lock is held', $tester->getDisplay());

        $deadWorker = $lockFactory->createLock($manager->getLockName(self::INDEX), 600);
        $this->assertTrue($deadWorker->acquire());

        // unknown index
        $this->assertSame(1, $tester->execute(['index' => 'nope'], ['interactive' => false]));

        // non interactive without --force: refused, lock kept
        $this->assertSame(1, $tester->execute(['index' => self::INDEX], ['interactive' => false]));
        $this->assertStringContainsString('Use --force', $tester->getDisplay());
        $this->assertTrue($manager->isLocked(self::INDEX));

        // "no" keeps the lock
        $tester->setInputs(['no']);
        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Aborted', $tester->getDisplay());
        $this->assertTrue($manager->isLocked(self::INDEX));

        // --force releases every locked index
        $this->assertSame(0, $tester->execute(['--force' => true], ['interactive' => false]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Populate lock(s) currently held: '.self::INDEX, $display);
        $this->assertStringContainsString('1 lock(s) released', $display);
        $this->assertFalse($manager->isLocked(self::INDEX));
    }

    private function populate(bool $catchExceptions = true, ?int $maxPerPage = null): int
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions($catchExceptions);

        $arguments = [
            'command' => 'fos:elastica:populate',
            '--index' => self::INDEX,
            '--no-interaction' => true,
        ];
        if (null !== $maxPerPage) {
            $arguments['--max-per-page'] = $maxPerPage;
        }

        return $application->run(new ArrayInput($arguments), new NullOutput());
    }

    private function withPostInsertListener(callable $listener, int $priority, callable $run): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostInsertObjectsEvent::class, $listener, $priority);
        try {
            $run();
        } finally {
            $dispatcher->removeListener(PostInsertObjectsEvent::class, $listener);
        }
    }

    private function createEntities(int $count): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        for ($i = 1; $i <= $count; ++$i) {
            $this->findOrCreateTagEntity('Entity '.$i, $workspace);
        }
        $this->em->clear();
    }

    private function createOpenPass(\DateTimeImmutable $createdAt, ?\DateTimeImmutable $endedAt = null, string $indexName = self::INDEX, ?string $runId = null): string
    {
        $pass = new PopulatePass();
        $pass->setIndexName($indexName);
        $pass->setRunId($runId);
        $pass->setMapping([]);
        $pass->setDocumentCount(4);
        $pass->setProgress(1);
        $pass->setEndedAt($endedAt);
        $this->em->persist($pass);
        $this->em->flush();

        // Gedmo Timestampable sets createdAt on insert
        $this->em->createQuery(sprintf('UPDATE %s p SET p.createdAt = :createdAt WHERE p.id = :id', PopulatePass::class))
            ->setParameter('createdAt', $createdAt, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $pass->getId())
            ->execute();

        return $pass->getId();
    }

    /**
     * The populate builds a dated index and switches the alias to it, as in production.
     */
    private function withAlias(): void
    {
        $this->useAlias(self::INDEX);
        $this->aliased = true;
        // the alias points to a first dated index
        $this->assertSame(0, $this->populate());
        $this->deletePasses();
        // the dated index name has a precision of one second: the next populate needs another one
        sleep(1);
    }

    private function createPhysicalIndex(string $name): void
    {
        $this->getEsClient()->indices()->create(['index' => $name]);
    }

    /**
     * @return list<PopulatePass>
     */
    private function getPasses(): array
    {
        $this->em->clear();

        return $this->em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX], ['createdAt' => 'ASC']);
    }

    private function deletePasses(): void
    {
        $this->em->createQuery(sprintf('DELETE FROM %s p WHERE p.indexName = :i', PopulatePass::class))
            ->setParameter('i', self::INDEX)
            ->execute();
        $this->em->clear();
    }
}
