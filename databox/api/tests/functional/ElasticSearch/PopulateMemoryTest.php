<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\ESBundle\Service\IndexRemover;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Elasticsearch\Listener\PopulatePassListener;
use App\Elasticsearch\Mapping\IndexSyncState;
use App\Elasticsearch\PopulateLockManager;
use App\Elasticsearch\PopulatePageTracker;
use App\Entity\Admin\PopulatePass;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetEmbedding;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\File;
use App\Entity\Core\FileMetadata;
use App\Repository\Core\AttributeRepository;
use App\Tests\Functional\DataboxTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PreInsertObjectsEvent;
use Sentry\Event;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Lock\LockFactory;

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
    private const int FACES_PER_ASSET = 3;
    private const int ASSETS_WITH_EMBEDDING = 3;

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
            [$code, $output] = $this->populate();
        } finally {
            $dispatcher->removeListener(PostInsertObjectsEvent::class, $listener);
        }

        $this->assertSame(0, $code, $output);
        $this->assertGreaterThan(4, \count($pages));
        $this->assertSame([], $leaks);

        $pass = $this->getPass();
        $this->assertNull($pass->getError());
        $this->assertSame(self::ASSET_COUNT, $pass->getProgress());

        $stats = $pass->getStats();
        $this->assertIsArray($stats);
        $this->assertSame(self::PER_PAGE, $stats['perPage']);
        $this->assertSame(intdiv(self::ASSET_COUNT, self::PER_PAGE), $stats['page']);
        $this->assertCount(self::PER_PAGE, $stats['pageIds']);
        $this->assertGreaterThan(0, $stats['maxPagePeak']);
        $this->assertNotNull($stats['maxPagePeakPage']);
        $this->assertGreaterThan(0, $stats['lastPageMemoryAfterGc']);
        $this->assertStringContainsString('at page 6', (string) $pass->getMemoryString());

        $index = static::getContainer()->get(IndexManager::class)->getIndex(self::INDEX);
        $index->refresh();
        $this->assertSame(self::ASSET_COUNT, $index->count());
    }

    /**
     * A fatal error (out of memory…) is not an exception: FOS Elastica cannot record it, the
     * process just dies. The page it was indexing is left on the pass and in the Sentry scope.
     */
    public function testAProcessDyingMidPageLeavesItsPageOnThePass(): void
    {
        $this->createAssets();

        $hub = static::getContainer()->get(HubInterface::class);
        $transformed = 0;
        $faultyAssetId = null;
        $sentryEvent = null;
        $listener = function (PostTransformEvent $event) use ($hub, &$transformed, &$faultyAssetId, &$sentryEvent): void {
            if (!($asset = $event->getObject()) instanceof Asset) {
                return;
            }
            if (13 === ++$transformed) {
                $faultyAssetId = $asset->getId();
                // What Sentry reports with the fatal error: the scope of the command is gone once it ends
                $hub->configureScope(function (Scope $scope) use (&$sentryEvent): void {
                    $sentryEvent = $scope->applyToEvent(Event::createEvent());
                });

                throw new \Error('simulated fatal');
            }
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostTransformEvent::class, $listener, -10);

        try {
            $this->populate();
            $this->fail('the error stops the populate');
        } catch (\Error $e) {
            $this->assertSame('simulated fatal', $e->getMessage());
        } finally {
            $dispatcher->removeListener(PostTransformEvent::class, $listener);
        }

        $pass = $this->getPass();
        $this->assertNull($pass->getEndedAt(), 'the pass of a dead process stays open');
        $stats = $pass->getStats();
        $this->assertIsArray($stats);
        $this->assertSame(3, $stats['page']);
        $this->assertSame(self::PER_PAGE, $stats['perPage']);
        $this->assertContains($faultyAssetId, $stats['pageIds']);

        $this->assertInstanceOf(Event::class, $sentryEvent);
        $context = $sentryEvent->getContexts()[PopulatePageTracker::SENTRY_CONTEXT] ?? null;
        $this->assertIsArray($context);
        $this->assertSame(3, $context['page']);
        $this->assertSame(self::INDEX, $context['index']);
        $this->assertContains($faultyAssetId, $context['pageIds']);
        $this->assertSame(self::INDEX, $sentryEvent->getTags()[PopulatePageTracker::SENTRY_TAG] ?? null);
    }

    /**
     * The face embeddings of the attribute values are never indexed: the populate must not
     * decode them (hundreds of floats per face). Nor the file metadata, which no fallback reads here.
     */
    public function testAPageIsIndexedWithoutDecodingTheEmbeddings(): void
    {
        $faces = $this->createAssets(withFaces: true);
        $this->assertInstanceOf(AttributeDefinition::class, $faces);

        $em = self::getEntityManager();
        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = $em->getRepository(Attribute::class);
        $embedding = new \ReflectionProperty(Attribute::class, 'embedding');

        $checkedFaces = 0;
        $loadedEmbeddings = [];
        $loadedEntities = [];
        // The assets created by the test are also indexed in real time, after the populate
        $inPage = false;
        $pageStarted = function () use (&$inPage): void {
            $inPage = true;
        };
        $pageEnded = function () use (&$inPage): void {
            $inPage = false;
        };
        $listener = function (PostTransformEvent $event) use ($em, $attributeRepository, $embedding, $faces, &$inPage, &$checkedFaces, &$loadedEmbeddings, &$loadedEntities): void {
            if (!$inPage || !($asset = $event->getObject()) instanceof Asset) {
                return;
            }

            /** @var Attribute $attribute */
            foreach ($attributeRepository->getCachedAssetAttributes($asset->getId()) as $attribute) {
                if ($attribute->getDefinition()->getId() !== $faces->getId()) {
                    continue;
                }
                ++$checkedFaces;
                if (!$embedding->isLazy($attribute)) {
                    $loadedEmbeddings[] = $attribute->getId();
                }
            }

            $uow = $em->getUnitOfWork();
            foreach ([AssetEmbedding::class, FileMetadata::class] as $class) {
                foreach ($uow->getIdentityMap()[$class] ?? [] as $entity) {
                    // The metadata of a loaded file is an uninitialized proxy: it is not loaded
                    if (!$uow->isUninitializedObject($entity)) {
                        $loadedEntities[] = $class;
                    }
                }
            }
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostTransformEvent::class, $listener, -10);
        $dispatcher->addListener(PreInsertObjectsEvent::class, $pageStarted, -2048);
        $dispatcher->addListener(PostInsertObjectsEvent::class, $pageEnded, 2048);

        try {
            [$code, $output] = $this->populate();
        } finally {
            $dispatcher->removeListener(PostTransformEvent::class, $listener);
            $dispatcher->removeListener(PreInsertObjectsEvent::class, $pageStarted);
            $dispatcher->removeListener(PostInsertObjectsEvent::class, $pageEnded);
        }

        $this->assertSame(0, $code, $output);
        $this->assertSame(self::ASSET_COUNT * self::FACES_PER_ASSET, $checkedFaces);
        $this->assertSame([], $loadedEmbeddings, 'the face embeddings are not loaded');
        $this->assertSame([], array_values(array_unique($loadedEntities)));

        $index = static::getContainer()->get(IndexManager::class)->getIndex(self::INDEX);
        $index->refresh();
        $this->assertSame(self::ASSET_COUNT, $index->count());

        $em->clear();
        $withEmbedding = 0;
        $assets = $em->getRepository(Asset::class)->findBy(['workspace' => $this->getOrCreateDefaultWorkspace()->getId()]);
        $this->assertCount(self::ASSET_COUNT, $assets);
        foreach ($assets as $asset) {
            $source = $index->getDocument($asset->getId())->getData();
            $this->assertIsArray($source);
            $json = json_encode($source, JSON_THROW_ON_ERROR);
            for ($f = 0; $f < self::FACES_PER_ASSET; ++$f) {
                $this->assertStringContainsString(sprintf('"Person %d"', $f), $json);
            }
            if (isset($source['embedding'])) {
                ++$withEmbedding;
                $this->assertCount(512, $source['embedding']);
            }
        }
        $this->assertSame(self::ASSETS_WITH_EMBEDDING, $withEmbedding);
    }

    public function testAPopulateRunningOutOfMemoryStopsWithAnError(): void
    {
        $this->createAssets();

        $container = static::getContainer();
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $listener = $container->get(PopulatePassListener::class);
        $guardedListener = new PopulatePassListener(
            $container->get(EntityManagerInterface::class),
            $container->get(IndexSyncState::class),
            $container->get(IndexRemover::class),
            $container->get(LockFactory::class),
            $container->get(PopulatePageTracker::class),
            memoryGuardRatio: 0.0001,
        );
        $dispatcher->removeSubscriber($listener);
        $dispatcher->addSubscriber($guardedListener);

        try {
            [$code, $output] = $this->populate();
        } finally {
            $dispatcher->removeSubscriber($guardedListener);
            $dispatcher->addSubscriber($listener);
        }

        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('Memory after page 1', $output);

        $pass = $this->getPass();
        $this->assertNotNull($pass->getEndedAt());
        $this->assertStringStartsWith(sprintf('Memory after page 1 of index "%s"', self::INDEX), (string) $pass->getError());
        $this->assertSame(1, $pass->getStats()['page'] ?? null);
        $this->assertSame(self::PER_PAGE, $pass->getProgress());
        $this->assertFalse($container->get(PopulateLockManager::class)->isLocked(self::INDEX), 'the lock is released');
    }

    /**
     * @return array{int, string}
     */
    private function populate(): array
    {
        $output = new BufferedOutput();
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput([
            'command' => 'fos:elastica:populate',
            '--index' => self::INDEX,
            '--max-per-page' => self::PER_PAGE,
            '--no-interaction' => true,
        ]), $output);

        return [$code, $output->fetch()];
    }

    private function getPass(): PopulatePass
    {
        $em = self::getEntityManager();
        $em->clear();
        $passes = $em->getRepository(PopulatePass::class)->findBy(['indexName' => self::INDEX]);
        $this->assertCount(1, $passes);

        return $passes[0];
    }

    /**
     * @return AttributeDefinition|null the definition of the faces
     */
    private function createAssets(bool $withFaces = false): ?AttributeDefinition
    {
        $em = self::getEntityManager();
        $workspace = $this->getOrCreateDefaultWorkspace();
        $collection = $this->createCollection(['name' => 'Memory']);
        $title = $this->createAttributeDefinition(['name' => 'Title', 'no_flush' => true]);
        $description = $this->createAttributeDefinition(['name' => 'Description', 'no_flush' => true]);
        $faces = $withFaces ? $this->createAttributeDefinition(['name' => 'Faces', 'multiple' => true, 'no_flush' => true]) : null;

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

            if (null === $faces) {
                continue;
            }

            for ($f = 0; $f < self::FACES_PER_ASSET; ++$f) {
                $face = new Attribute();
                $face->setAsset($asset);
                $face->setDefinition($faces);
                $face->setPosition($f);
                $face->setOrigin(Attribute::ORIGIN_MACHINE);
                $face->setValue(sprintf('Person %d', $f));
                $face->setEmbedding(['m' => 'test', 'v' => array_fill(0, 512, 0.1 + $f / 10)]);
                $em->persist($face);
            }

            if ($i < self::ASSETS_WITH_EMBEDDING) {
                $assetEmbedding = new AssetEmbedding();
                $assetEmbedding->setAsset($asset);
                $assetEmbedding->setModel('test');
                $assetEmbedding->setVector(array_fill(0, 512, 0.2));
                $em->persist($assetEmbedding);
            }
        }

        $em->flush();
        $em->clear();

        return $faces;
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
