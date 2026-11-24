<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\ESBundle\Indexer\IndexPersister;
use Alchemy\ESBundle\Listener\DeferredIndexListener;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Doctrine\Delete\AssetDelete;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use App\Entity\Core\Asset;
use App\Listener\AclAssetIndexUpdateService;
use App\Tests\Functional\DataboxTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Elastica\Index;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A populate fills a new physical index and only switches the alias to it at the end:
 * the live writes made meanwhile go to the old index. Once the alias points to the new
 * index, they must be found there too (a revoked right, a deleted asset…).
 */
final class PopulateReplayTest extends KernelTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;
    use AliasedIndexTestTrait;

    private const string INDEX = 'asset';

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->deletePasses();
        $this->useAlias(self::INDEX);
    }

    protected function tearDown(): void
    {
        $this->deletePasses();
        static::getContainer()->get(PopulateLockManager::class)->forceRelease(self::INDEX, evenIfAlive: true);
        $this->restorePlainIndex(self::INDEX);
        $this->defaultWorkspace = null;
        $this->defaultAttributePolicy = null;
        parent::tearDown();
    }

    public function testTheWritesMadeDuringThePopulateAreReplayedOnTheNewIndex(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $workspaceId = $workspace->getId();
        $renamedId = $this->createAsset(['name' => 'Before', 'workspace' => $workspace])->getId();
        $deletedId = $this->createAsset(['name' => 'Deleted', 'workspace' => $workspace])->getId();
        $grantedId = $this->createAsset(['name' => 'Granted', 'workspace' => $workspace])->getId();
        $em->clear();

        $this->assertSame(0, $this->populate());
        $this->deletePasses();
        // the dated index name has a precision of one second: the next populate needs another one
        sleep(1);
        $aliasedBefore = $this->getAliasedIndex(self::INDEX);
        $this->assertNotNull($aliasedBefore);

        $written = false;
        // after the last page: every document is copied to the new index, with its former state
        $listener = function (PostInsertObjectsEvent $event) use (&$written, $renamedId, $deletedId, $workspaceId): void {
            $pager = $event->getPager();
            if ($written || $pager->getCurrentPage() < $pager->getNbPages()) {
                return;
            }
            $written = true;

            $this->writeToTheOldIndex(function () use ($renamedId, $deletedId, $workspaceId): void {
                $container = static::getContainer();
                $em = $container->get(EntityManagerInterface::class);

                // reindexed document by document (e.g. a revoked right)
                $asset = $em->find(Asset::class, $renamedId);
                $asset->name = 'After';
                DeferredIndexListener::disable();
                try {
                    $em->flush();
                } finally {
                    DeferredIndexListener::enable();
                }
                $container->get(IndexPersister::class)->replaceMany(Asset::class, [$asset]);

                // deleted by query
                $container->get(AssetDelete::class)->deleteAssets([$deletedId]);

                // updated by query (a right granted on the workspace)
                $container->get(AclAssetIndexUpdateService::class)
                    ->addAllowedUserOrGroupToWorkspace($workspaceId, AccessControlEntryInterface::TYPE_USER_VALUE, 'granted-user');
            });
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostInsertObjectsEvent::class, $listener, -2048);
        try {
            $this->assertSame(0, $this->populate());
        } finally {
            $dispatcher->removeListener(PostInsertObjectsEvent::class, $listener);
        }

        $this->assertTrue($written);
        $this->assertNotSame($aliasedBefore, $this->getAliasedIndex(self::INDEX), 'the alias points to the new index');

        $documents = $this->getDocuments([$renamedId, $deletedId, $grantedId]);
        $this->assertSame('After', $documents[$renamedId]['suggestion'] ?? null, 'the document reindexed during the populate is up to date');
        $this->assertArrayNotHasKey($deletedId, $documents, 'the document deleted during the populate is gone');
        $this->assertContains('granted-user', $documents[$grantedId]['users'] ?? [], 'the right granted during the populate is there');

        $em->clear();
        $passes = $em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX]);
        $this->assertCount(1, $passes);
        $this->assertNull($passes[0]->getError());
        $this->assertSame(2, $passes[0]->getStats()['replayedQueries'] ?? null);
        $this->assertSame(1, $passes[0]->getStats()['replayedDocuments'] ?? null);

        $this->assertFalse(static::getContainer()->get(PopulateLockManager::class)->isLocked(self::INDEX));
    }

    public function testNothingIsReplayedWithoutWritesDuringThePopulate(): void
    {
        $this->createAsset(['name' => 'Quiet']);
        $this->assertSame(0, $this->populate());
        $this->deletePasses();
        sleep(1);

        // written before the populate: already in the database it copies
        static::getContainer()->get(AclAssetIndexUpdateService::class)
            ->addAllowedUserOrGroupToWorkspace(null, AccessControlEntryInterface::TYPE_USER_VALUE, 'before-user');

        $this->assertSame(0, $this->populate());

        $em = self::getEntityManager();
        $em->clear();
        $pass = $em->getRepository(PopulatePass::class)->findOneBy(['indexName' => self::INDEX]);
        $this->assertSame(0, $pass->getStats()['replayedQueries'] ?? null);
        $this->assertSame(0, $pass->getStats()['replayedDocuments'] ?? null);
    }

    /**
     * In this process, the populated index is the one FOS Elastica writes to: the live
     * writes of the other processes go through the alias, to the old index.
     */
    private function writeToTheOldIndex(callable $write): void
    {
        /** @var Index $index */
        $index = static::getContainer()->get('fos_elastica.index.'.self::INDEX);
        $builtIndex = $index->getName();
        $index->overrideName($this->getAliasName(self::INDEX));
        try {
            $write();
        } finally {
            $index->overrideName($builtIndex);
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array<string, mixed>> id => source, from the index the alias points to
     */
    private function getDocuments(array $ids): array
    {
        $client = $this->getEsClient();
        $client->indices()->refresh(['index' => $this->getAliasName(self::INDEX)]);
        $response = $client->search([
            'index' => $this->getAliasName(self::INDEX),
            'body' => [
                'query' => ['ids' => ['values' => $ids]],
                'size' => \count($ids),
            ],
        ])->asArray();

        $documents = [];
        foreach ($response['hits']['hits'] as $hit) {
            $documents[$hit['_id']] = $hit['_source'];
        }

        return $documents;
    }

    private function populate(): int
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        return $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
            '--index' => self::INDEX,
            '--no-interaction' => true,
        ]), new NullOutput());
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
