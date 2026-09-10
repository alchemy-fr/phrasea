<?php

declare(strict_types=1);

namespace App\Tests\Elasticsearch;

use App\Elasticsearch\Exception\PopulateAlreadyRunningException;
use App\Elasticsearch\Listener\PopulatePassListener;
use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Lock\LockFactory;

/**
 * Two populates of the same index must never run at the same time: the second
 * one is refused (and recorded as a failed pass) instead of deleting the pass
 * and the half-built physical index of the first one.
 */
class PopulateLockTest extends KernelTestCase
{
    private const INDEX = 'tag';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->deletePasses();
    }

    protected function tearDown(): void
    {
        $this->deletePasses();
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

    private function populate(bool $catchExceptions = true): int
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions($catchExceptions);

        return $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
            '--index' => self::INDEX,
            '--no-interaction' => true,
        ]), new NullOutput());
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
