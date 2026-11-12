<?php

declare(strict_types=1);

namespace App\Tests\Functional\ElasticSearch;

use Alchemy\ApiTest\ApiTestTrait;
use Alchemy\TestBundle\Helper\FixturesTrait;
use App\Elasticsearch\PopulateLockManager;
use App\Entity\Admin\PopulatePass;
use App\Entity\Core\Collection;
use App\Tests\Functional\DataboxTestTrait;
use Doctrine\ORM\PersistentCollection;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The "hasChildren" flag of a collection document is computed without loading its children.
 */
final class CollectionPostTransformListenerTest extends KernelTestCase
{
    use FixturesTrait;
    use DataboxTestTrait;
    use ApiTestTrait;

    private const string INDEX = 'collection';

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

    public function testHasChildrenDoesNotLoadTheChildren(): void
    {
        $parent = $this->createCollection(['name' => 'Parent']);
        $this->createCollection(['name' => 'Child 1', 'parent' => $parent]);
        $this->createCollection(['name' => 'Child 2', 'parent' => $parent]);
        $leaf = $this->createCollection(['name' => 'Leaf']);
        self::getEntityManager()->clear();

        $expected = [$parent->getId() => true, $leaf->getId() => false];
        $hasChildren = [];
        $initializedChildren = [];
        $listener = function (PostTransformEvent $event) use ($expected, &$hasChildren, &$initializedChildren): void {
            if (!($collection = $event->getObject()) instanceof Collection || !isset($expected[$collection->getId()])) {
                return;
            }

            $hasChildren[$collection->getId()] = $event->getDocument()->get('hasChildren');
            $children = $collection->getChildren();
            if (!$children instanceof PersistentCollection || $children->isInitialized()) {
                $initializedChildren[] = $collection->getId();
            }
        };

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PostTransformEvent::class, $listener, -10);

        try {
            $output = new BufferedOutput();
            $application = new Application(static::$kernel);
            $application->setAutoExit(false);
            $code = $application->run(new ArrayInput([
                'command' => 'fos:elastica:populate',
                '--index' => self::INDEX,
                '--no-interaction' => true,
            ]), $output);
        } finally {
            $dispatcher->removeListener(PostTransformEvent::class, $listener);
        }

        $this->assertSame(0, $code, $output->fetch());
        ksort($expected);
        ksort($hasChildren);
        $this->assertSame($expected, $hasChildren);
        $this->assertSame([], $initializedChildren, 'the children are not loaded');
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
