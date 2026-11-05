<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Command\ESPopulateUnlockCommand;
use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Listener\PopulatePassListener;
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

    private const INDEX = 'tag';

    private EntityManagerInterface $em;

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
        $this->defaultWorkspace = null;
        $this->defaultAttributePolicy = null;
        parent::tearDown();
    }

    public function testPopulateIsRefusedWhileTheIndexIsLocked(): void
    {
        /** @var LockFactory $lockFactory */
        $lockFactory = static::getContainer()->get(LockFactory::class);
        $otherProcess = $lockFactory->createLock(PopulatePassListener::getLockName(self::INDEX), 60);
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

        $deadWorker = $lockFactory->createLock(PopulatePassListener::getLockName(self::INDEX), 600);
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

    public function testPopulateProgressIsRecordedPageByPage(): void
    {
        $this->createTags(3);

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
        $this->createTags(3);

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

        $before = $this->createOpenPass(new \DateTimeImmutable('-1 hour'));
        $closedBefore = $this->createOpenPass(new \DateTimeImmutable('-1 hour'), endedAt: new \DateTimeImmutable('-30 minutes'));
        $since = new \DateTimeImmutable('-1 minute');
        $dead = $this->createOpenPass(new \DateTimeImmutable());
        $closedAfter = $this->createOpenPass(new \DateTimeImmutable(), endedAt: new \DateTimeImmutable());
        $otherIndex = $this->createOpenPass(new \DateTimeImmutable(), indexName: 'collection');
        $this->em->clear();

        $deadWorker = $lockFactory->createLock(PopulatePassListener::getLockName(self::INDEX), 600);
        $this->assertTrue($deadWorker->acquire());

        try {
            $this->assertSame(1, $manager->closeDeadPasses(self::INDEX, $since, 'Process killed'));
            $this->assertFalse($manager->isLocked(self::INDEX), 'the lock of the dead run is released');

            $this->em->clear();
            $closed = $this->em->find(PopulatePass::class, $dead);
            $this->assertNotNull($closed->getEndedAt());
            $this->assertSame('Process killed', $closed->getError());

            $this->assertNull($this->em->find(PopulatePass::class, $before)->getEndedAt(), 'a pass older than the run is left alone');
            $this->assertNull($this->em->find(PopulatePass::class, $closedBefore)->getError());
            $this->assertNull($this->em->find(PopulatePass::class, $closedAfter)->getError(), 'a closed pass is left alone');
            $this->assertNull($this->em->find(PopulatePass::class, $otherIndex)->getEndedAt(), 'another index is left alone');

            // without index: every open pass of the run
            $this->assertSame(1, $manager->closeDeadPasses(null, $since, 'Process killed'));
            $this->em->clear();
            $this->assertNotNull($this->em->find(PopulatePass::class, $otherIndex)->getEndedAt());
            $this->assertNull($this->em->find(PopulatePass::class, $before)->getEndedAt());
        } finally {
            $deadWorker->release();
            $this->em->createQuery(sprintf('DELETE FROM %s p WHERE p.indexName = :i', PopulatePass::class))
                ->setParameter('i', 'collection')
                ->execute();
        }
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

        $deadWorker = $lockFactory->createLock(PopulatePassListener::getLockName(self::INDEX), 600);
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

    private function createTags(int $count): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        for ($i = 1; $i <= $count; ++$i) {
            $this->findOrCreateTagByName('Tag '.$i, $workspace);
        }
        $this->em->clear();
    }

    private function createOpenPass(\DateTimeImmutable $createdAt, ?\DateTimeImmutable $endedAt = null, string $indexName = self::INDEX): string
    {
        $pass = new PopulatePass();
        $pass->setIndexName($indexName);
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
