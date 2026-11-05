<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use App\Entity\Core\Asset;
use App\Entity\Core\File;
use App\Tests\Functional\DataboxTestTrait;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The memory of a populate must not grow with the number of indexed pages: once a page
 * is inserted, nothing may keep its entities (nor their big metadata arrays) alive.
 *
 * The test does not collect the garbage cycles itself: the Doctrine graph is full of
 * cycles, so a page only dies if the populate collects them.
 */
final class PopulateMemoryTest extends KernelTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;

    private const string INDEX = 'asset';
    private const int ASSET_COUNT = 30;
    private const int PER_PAGE = 5;
    private const int METADATA_TAGS = 300;

    #[\Override]
    protected static function bootKernel(array $options = []): KernelInterface
    {
        return static::bootKernelWithFixtures($options);
    }

    protected function setUp(): void
    {
        // Without debug: the Doctrine debug middleware would keep the parameters of every query
        self::bootKernel(['debug' => false]);
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

    public function testIndexedPagesAreReleased(): void
    {
        $this->createAssets();

        /** @var list<list<array{string, \WeakReference<object>}>> $pages */
        $pages = [];
        $leaks = [];
        $listener = function (PostInsertObjectsEvent $event) use (&$pages, &$leaks): void {
            if (self::INDEX !== $event->getOptions()['indexName']) {
                return;
            }

            $pageNumber = \count($pages) + 1;
            foreach ($pages as $previousPageIndex => $references) {
                foreach ($references as [$label, $reference]) {
                    if (null !== $reference->get()) {
                        $leaks[] = sprintf('%s of page %d still alive at page %d', $label, $previousPageIndex + 1, $pageNumber);
                    }
                }
            }

            $references = [];
            foreach ($event->getObjects() as $asset) {
                $this->assertInstanceOf(Asset::class, $asset);
                $references[] = ['Asset '.$asset->getId(), \WeakReference::create($asset)];
                $source = $asset->getSource();
                $this->assertInstanceOf(File::class, $source);
                $references[] = ['File '.$source->getId(), \WeakReference::create($source)];
            }
            $pages[] = $references;
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostInsertObjectsEvent::class, $listener, -2048);

        try {
            $output = new BufferedOutput();
            $application = new Application(static::$kernel);
            $application->setAutoExit(false);
            $code = $application->run(new ArrayInput([
                'command' => 'fos:elastica:populate',
                '--index' => self::INDEX,
                '--max-per-page' => self::PER_PAGE,
                '--no-interaction' => true,
            ]), $output);
        } finally {
            $dispatcher->removeListener(PostInsertObjectsEvent::class, $listener);
        }

        $this->assertSame(0, $code, $output->fetch());
        $this->assertGreaterThan(4, \count($pages));
        $this->assertSame([], $leaks);

        $em = self::getEntityManager();
        $em->clear();
        $passes = $em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX]);
        $this->assertCount(1, $passes);
        $this->assertNull($passes[0]->getError());
        $this->assertSame(self::ASSET_COUNT, $passes[0]->getProgress());

        $index = static::getContainer()->get(IndexManager::class)->getIndex(self::INDEX);
        $index->refresh();
        $this->assertSame(self::ASSET_COUNT, $index->count());
    }

    private function createAssets(): void
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $collection = $this->createCollection(['name' => 'Memory']);
        $title = $this->createAttributeDefinition(['name' => 'Title', 'no_flush' => true]);
        $description = $this->createAttributeDefinition(['name' => 'Description', 'no_flush' => true]);

        for ($i = 0; $i < self::ASSET_COUNT; ++$i) {
            $file = new File();
            $file->setWorkspace($workspace);
            $file->setStorage(File::STORAGE_URL);
            $file->setPath(sprintf('https://example.com/%d.jpg', $i));
            $file->setPathPublic(true);
            $file->setType('image/jpeg');
            $file->setExtension('jpg');
            $file->setOriginalName(sprintf('photo-%d.jpg', $i));
            $file->setSize(1234);
            $file->setMetadata($this->createMetadata($i));
            $em->persist($file);

            $asset = $this->createAsset([
                'name' => sprintf('Asset %d', $i),
                'workspace' => $workspace,
                'collectionId' => $collection->getId(),
                'attributes' => [
                    ['definition' => $title, 'value' => sprintf('Title %d', $i)],
                    ['definition' => $description, 'value' => str_repeat('Lorem ipsum ', 50)],
                ],
                'no_flush' => true,
            ]);
            $asset->setSource($file);
        }

        $em->flush();
        $em->clear();
    }

    /**
     * @return array<string, array<string, array{value: string, values: list<string>}>>
     */
    private function createMetadata(int $seed): array
    {
        $metadata = [];
        for ($i = 0; $i < self::METADATA_TAGS; ++$i) {
            $value = sprintf('value %d of file %d %s', $i, $seed, str_repeat('x', 64));
            $metadata['Group'.($i % 10)]['Tag'.$i] = ['value' => $value, 'values' => [$value]];
        }

        return $metadata;
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
