<?php

declare(strict_types=1);

namespace App\Tests\Functional\Consumer\Handler\Search;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Consumer\Handler\Search\ESPopulate;
use App\Consumer\Handler\Search\ESPopulateHandler;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use App\Tests\Functional\DataboxTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * The populate asked from the admin runs in a child process (same environment, same
 * test database), so that the worker gets its memory back at the end of the run.
 */
final class ESPopulateHandlerTest extends KernelTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;

    private const string INDEX = 'tag';

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->deletePasses();
    }

    protected function tearDown(): void
    {
        $this->deletePasses();
        static::getContainer()->get(PopulateLockManager::class)->forceRelease(self::INDEX);
        $this->defaultWorkspace = null;
        $this->defaultAttributePolicy = null;
        parent::tearDown();
    }

    public function testPopulateRunsInAChildProcess(): void
    {
        $workspace = $this->getOrCreateDefaultWorkspace();
        foreach (['Red', 'Green', 'Blue'] as $name) {
            $this->findOrCreateTagByName($name, $workspace);
        }

        $this->getHandler()(new ESPopulate(self::INDEX));

        $em = self::getEntityManager();
        $em->clear();
        $passes = $em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX]);
        $this->assertCount(1, $passes);
        $this->assertNotNull($passes[0]->getEndedAt());
        $this->assertNull($passes[0]->getError());
        $this->assertSame(3, $passes[0]->getProgress());
        $this->assertFalse(static::getContainer()->get(PopulateLockManager::class)->isLocked(self::INDEX));
    }

    public function testAFailedProcessIsNotRetried(): void
    {
        try {
            $this->getHandler()(new ESPopulate('unknown-index'));
            $this->fail('the failure of the child process is reported');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringContainsString('exit code 1', $e->getMessage());
            $this->assertStringContainsString('unknown-index', $e->getMessage(), 'the end of the error output is reported');
        }
    }

    private function getHandler(): ESPopulateHandler
    {
        return static::getContainer()->get(ESPopulateHandler::class);
    }

    private function deletePasses(): void
    {
        $em = self::getEntityManager();
        $em->createQuery(sprintf('DELETE FROM %s p WHERE p.indexName = :i', PopulatePass::class))
            ->setParameter('i', self::INDEX)
            ->execute();
        $em->clear();
    }
}
