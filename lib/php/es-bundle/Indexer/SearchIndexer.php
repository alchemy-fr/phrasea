<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

use Alchemy\ESBundle\Message\ESIndex;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(KernelEvents::TERMINATE, method: 'flush', priority: -255)]
#[AsEventListener(ConsoleEvents::TERMINATE, method: 'flush', priority: -255)]
#[AsEventListener(WorkerMessageHandledEvent::class, method: 'flush', priority: -255)]
final class SearchIndexer
{
    private DependencyStacks $dependencyStacks;

    /**
     * @param IndexableDependenciesResolverInterface[] $dependenciesResolvers
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $em,
        private LoggerInterface $logger,
        private readonly IndexPersister $indexPersister,
        #[TaggedIterator(IndexableDependenciesResolverInterface::TAG)]
        private readonly iterable $dependenciesResolvers,
        private readonly bool $direct,
        private readonly int $maxDependencyStacksCount = 5,
        private readonly int $batchSize = 100,
        private readonly int $maxPerMessage = 200,
        private readonly int $maxDepth = 10,
    ) {
        $this->dependencyStacks = new DependencyStacks();
    }

    public function hasObjectPersisterFor(string $class): bool
    {
        return $this->indexPersister->hasObjectPersisterFor($class);
    }

    public function scheduleIndex(array $objects, int $depth = 1, array $parents = []): void
    {
        if (!$this->direct) {
            $this->messageBus->dispatch(new ESIndex($objects, $depth, $parents));

            return;
        }

        $this->index($objects, $depth, $parents);
    }

    public function scheduleObjectsIndex(string $class, array $ids, Operation $operation): void
    {
        $objects = [
            $class => [
                $operation->value => $ids,
            ],
        ];

        $this->scheduleIndex($objects);
    }

    /**
     * @internal used by consumer only
     */
    public function index(array $objects, int $depth, array $parents): void
    {
        if ($depth > $this->maxDepth) {
            $error = sprintf('%s: Max depth reached', self::class);
            if (!$this->direct) {
                $this->logger->emergency($error);
            } else {
                throw new \RuntimeException($error);
            }

            return;
        }

        foreach ($objects as $class => $entities) {
            foreach ($entities as $operation => $ids) {
                $op = Operation::from($operation);
                $chunks = array_chunk($ids, $this->batchSize);
                foreach ($chunks as $chunk) {
                    $this->indexClass($class, $chunk, $op, $depth, $objects, $parents);
                }
            }
        }
    }

    private function indexClass(string $class, array $ids, Operation $operation, int $depth, array $currentBatch, array $parents): void
    {
        $class = ClassUtils::getRealClass($class);
        $ids = array_unique($ids);

        $this->logger->debug(sprintf('ES index %s %s: ("%s")', $class, $operation->name, implode('", "', $ids)));

        switch ($operation) {
            case Operation::Delete:
                $this->indexPersister->deleteManyByIdentifiers($class, $ids);
                break;
            case Operation::Insert:
            case Operation::Upsert:
                $objects = $this->em
                    ->createQueryBuilder()
                    ->select('t')
                    ->from($class, 't')
                    ->andWhere('t.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->getQuery()
                    ->getResult();

                if (empty($objects)) {
                    $this->logger->alert('No document found for index', [
                        'class' => $class,
                        'ids' => implode(', ', $ids),
                    ]);

                    return;
                }

                if (count($objects) !== count($ids)) {
                    $this->logger->alert('Some documents were not found for index', [
                        'class' => $class,
                    ]);
                }

                if (Operation::Insert === $operation) {
                    $this->indexPersister->insertMany($class, $objects);
                } else {
                    $this->indexPersister->replaceMany($class, $objects);
                }

                foreach ($objects as $object) {
                    if ($object instanceof ESIndexableDependencyInterface) {
                        if (!empty($this->dependenciesResolvers)) {
                            $this->updateDependencies($object, $operation, $depth, $currentBatch, $parents);
                        }
                    }
                }

                break;
        }
    }

    private function updateDependencies(ESIndexableDependencyInterface $object, Operation $operation, int $depth, array $currentBatch, array $parents): void
    {
        $depStack = new DependencyStack(function (DependencyStack $stack, DependencyStack $relay): void {
            $this->flushDependencies(0);
            $this->dependencyStacks->addStack($relay);
        }, $this->maxPerMessage, $depth, $currentBatch, $parents);

        /** @var IndexableDependenciesResolverInterface $resolver */
        foreach ($this->dependenciesResolvers as $resolver) {
            $resolver->setDependencyStack($depStack);
            $resolver->updateDependencies($object, $operation);
        }

        if ($depStack->getDependencyCount() > 0) {
            $this->dependencyStacks->addStack($depStack);
        }
    }

    public function flush(): void
    {
        $this->flushDependencies(0);
    }

    private function flushDependencies(int $i): void
    {
        if ($i > $this->maxDependencyStacksCount) {
            throw new \RuntimeException(sprintf('%s error: Infinite loop detected in flush', self::class));
        }

        $stacks = $this->dependencyStacks->flush();

        if (!empty($stacks)) {
            foreach ($stacks as $stack) {
                $this->flushDependencyStack($stack);
            }

            $this->flushDependencies($i + 1);
        }
    }

    /**
     * @param ESIndexableInterface[]|array $entities
     */
    public static function computeObjects(array &$objects, array $entities, Operation $operation): void
    {
        foreach ($entities as $entity) {
            if (is_array($entity)) {
                [$class, $id] = $entity;
            } else {
                $class = ClassUtils::getRealClass($entity::class);
                $id = $entity->getId();
            }

            if (!isset($objects[$class])) {
                $objects[$class] = [];
            }
            if (!isset($objects[$class][$operation->value])) {
                $objects[$class][$operation->value] = [];
            }
            if (!in_array($id, $objects[$class][$operation->value], true)) {
                $objects[$class][$operation->value][] = $id;
            }
        }
    }

    private function flushDependencyStack(DependencyStack $dependencyStack): void
    {
        if ($dependencyStack->getDependencyCount() > 0) {
            $this->scheduleIndex($dependencyStack->getObjects(), $dependencyStack->getDepth() + 1, $dependencyStack->getParents());
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
