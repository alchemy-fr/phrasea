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
    private const MAX_DEPTH = 10;
    private const BATCH_SIZE = 100;
    private const MAX_PER_MESSAGE = 200;

    final public const ACTION_INSERT = 'i';
    final public const ACTION_UPSERT = 'u';
    final public const ACTION_DELETE = 'd';

    private array $dependenciesStack = [];
    private array $dependenciesParents = [];
    private int $dependenciesCount = 0;

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
    ) {
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

    public function scheduleObjectsIndex(string $class, array $ids, string $operation): void
    {
        $objects = [
            $class => [
                $operation => $ids,
            ],
        ];

        $this->scheduleIndex($objects);
    }

    /**
     * @internal used by consumer only
     */
    public function index(array $objects, int $depth, array $parents): void
    {
        if ($depth > self::MAX_DEPTH) {
            $this->logger->emergency(sprintf('%s: Max depth reached', self::class));

            return;
        }

        foreach ($objects as $class => $entities) {
            foreach ($entities as $operation => $ids) {
                $chunks = array_chunk($ids, self::BATCH_SIZE);
                foreach ($chunks as $chunk) {
                    $this->indexClass($class, $chunk, $operation, $depth, $objects, $parents);
                }
            }
        }
    }

    private function indexClass(string $class, array $ids, string $operation, int $depth, array $currentBatch, array $parents): void
    {
        $class = ClassUtils::getRealClass($class);
        $ids = array_unique($ids);

        $this->logger->debug(sprintf('ES index %s %s: ("%s")', $class, $operation, implode('", "', $ids)));

        switch ($operation) {
            case self::ACTION_DELETE:
                $this->indexPersister->deleteManyByIdentifiers($class, $ids);
                break;
            case self::ACTION_INSERT:
            case self::ACTION_UPSERT:
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
                        'ids' => implode(', ', $ids)
                    ]);

                    return;
                }

                if (count($objects) !== count($ids)) {
                    $this->logger->alert('Some documents were not found for index', [
                        'class' => $class,
                    ]);
                }

                if (self::ACTION_INSERT === $operation) {
                    $this->indexPersister->insertMany($class, $objects);
                } else {
                    $this->indexPersister->replaceMany($class, $objects);
                }

                foreach ($objects as $object) {
                    if ($object instanceof ESIndexableDependencyInterface) {
                        $this->updateDependencies($object, $depth, $currentBatch, $parents);
                    }
                }

                break;
        }
    }

    private function updateDependencies(ESIndexableDependencyInterface $object, int $depth, array $currentBatch, array $parents): void
    {
        /** @var IndexableDependenciesResolverInterface $resolver */
        foreach ($this->dependenciesResolvers as $resolver) {
            $resolver->setAddToParentsClosure(function (string $class, string $id): void {
                $this->addToParents($class, $id);
            });
            $resolver->setAddDependencyClosure(
                fn (string $class, string $id) => $this->addDependency($class, $id, $depth, $currentBatch, $parents)
            );

            $resolver->updateDependencies($object);
        }
    }

    public function flush(): void
    {
        $i = 0;
        while (!empty($this->dependenciesStack)) {
            $this->flushDependenciesStack(0);
            $this->dependenciesParents = [];

            ++$i;

            if ($i++ > 100) {
                throw new \RuntimeException(sprintf('%s error: Infinite loop detected in flush', self::class));
            }
        }
    }

    /**
     * @param ESIndexableInterface[]|array $entities
     */
    public static function computeObjects(array &$objects, array $entities, string $operation): void
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
            if (!isset($objects[$class][$operation])) {
                $objects[$class][$operation] = [];
            }
            if (!in_array($id, $objects[$class][$operation], true)) {
                $objects[$class][$operation][] = $id;
            }
        }
    }

    private function isInBatch(string $class, string $id, array $currentBatch): bool
    {
        if (!isset($currentBatch[$class])) {
            return false;
        }

        $b = $currentBatch[$class];

        return in_array($id, $b[self::ACTION_UPSERT] ?? $b[self::ACTION_INSERT] ?? [], true);
    }

    public function addDependency(string $class, string $id, int $depth, array $currentBatch, array $parents): void
    {
        if ($this->isInBatch($class, $id, $currentBatch)) {
            return;
        }

        if (isset($parents[$class]) && in_array($id, $parents[$class], true)) {
            return;
        }

        $this->dependenciesStack[$class] ??= [];
        $this->dependenciesStack[$class][self::ACTION_UPSERT] ??= [];

        if (!in_array($id, $this->dependenciesStack[$class][self::ACTION_UPSERT], true)) {
            $this->dependenciesStack[$class][self::ACTION_UPSERT][] = $id;
            ++$this->dependenciesCount;

            if ($this->dependenciesCount >= self::MAX_PER_MESSAGE) {
                $this->flushDependenciesStack($depth);
            }
        }
    }

    private function addToParents(string $class, string $id): void
    {
        $this->dependenciesParents[$class][$id] = true;
    }

    private function flushDependenciesStack(int $depth): void
    {
        if (!empty($this->dependenciesStack)) {
            $parents = array_map(fn (array $list): array => array_keys($list), $this->dependenciesParents);
            $objects = $this->dependenciesStack;
            $this->dependenciesStack = [];
            $this->dependenciesParents = [];
            $this->dependenciesCount = 0;
            $this->scheduleIndex($objects, $depth + 1, $parents);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
