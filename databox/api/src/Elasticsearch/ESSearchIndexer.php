<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Consumer\Handler\Search\SearchIndexHandler;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\SearchDependencyInterface;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;

class ESSearchIndexer
{
    private const MAX_DEPTH = 10;
    private const BATCH_SIZE = 100;
    private const MAX_PER_MESSAGE = 200;

    final public const ACTION_INSERT = 'i';
    final public const ACTION_UPSERT = 'u';
    final public const ACTION_DELETE = 'd';

    /**
     * @var ObjectPersisterInterface[][]
     */
    protected array $objectPersisters = [];

    private array $dependenciesStack = [];
    private int $dependenciesCount = 0;

    public function __construct(
        private readonly EventProducer $eventProducer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly bool $direct = false
    ) {
    }

    public function addObjectPersister(string $class, ObjectPersisterInterface $objectPersister): void
    {
        if (!isset($this->objectPersisters[$class])) {
            $this->objectPersisters[$class] = [];
        }
        $this->objectPersisters[$class][] = $objectPersister;
    }

    public function hasObjectPersisterFor(string $class): bool
    {
        return !empty($this->objectPersisters[$class]);
    }

    public function scheduleIndex(array $objects, int $depth = 1): void
    {
        if (!$this->direct) {
            $this->eventProducer->publish(new EventMessage(SearchIndexHandler::EVENT, [
                'objects' => $objects,
                'depth' => $depth,
            ]));

            return;
        }

        $this->index($objects, $depth);
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
    public function index(array $objects, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $this->logger->emergency(sprintf('%s: Max depth reached', self::class));

            return;
        }

        foreach ($objects as $class => $entities) {
            foreach ($entities as $operation => $ids) {
                $chunks = array_chunk($ids, self::BATCH_SIZE);
                foreach ($chunks as $chunk) {
                    $this->indexClass($class, $chunk, $operation, $depth, $objects);
                    if (!$this->direct) {
                        $this->em->clear();
                    }
                }
            }
        }
    }

    private function indexClass(string $class, array $ids, string $operation, int $depth, array $currentBatch): void
    {
        $class = ClassUtils::getRealClass($class);
        $persisters = $this->objectPersisters[$class] ?? [];

        $ids = array_unique($ids);

        $this->logger->debug(sprintf('ES index %s %d: ("%s")', $class, $operation, implode('", "', $ids)));

        switch ($operation) {
            case self::ACTION_DELETE:
                foreach ($persisters as $persister) {
                    $persister->deleteManyByIdentifiers($ids);
                }
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
                    $this->logger->alert(sprintf('No %s document found for index', $class));

                    return;
                }

                if ((is_countable($objects) ? count($objects) : 0) !== count($ids)) {
                    $this->logger->alert(sprintf('Some %s documents were not found for index', $class));
                }

                foreach ($persisters as $persister) {
                    if (self::ACTION_INSERT === $operation) {
                        $persister->insertMany($objects);
                    } else {
                        $persister->replaceMany($objects);
                    }
                }

                foreach ($objects as $object) {
                    if ($object instanceof SearchDependencyInterface) {
                        $this->updateDependencies($object, $depth, $currentBatch);
                    }
                }

                break;
        }
    }

    private function updateDependencies(SearchDependencyInterface $object, int $depth, array $currentBatch): void
    {
        if ($object instanceof Collection) {
            $this->appendDependencyEntities(
                Asset::class,
                $this->em->getRepository(Asset::class)
                    ->getCollectionAssets($object->getId()),
                $depth,
                $currentBatch
            );
            if ($object->getParent()) {
                $this->addDependency(Collection::class, $object->getParent()->getId(), $depth, $currentBatch);
            }
        } elseif ($object instanceof CollectionAsset) {
            $this->addDependency(Asset::class, $object->getAsset()->getId(), $depth, $currentBatch);
        } elseif ($object instanceof Attribute) {
            $this->addDependency(Asset::class, $object->getAsset()->getId(), $depth, $currentBatch);
        }
    }

    public function flush(): void
    {
        $i = 0;
        while (!empty($this->dependenciesStack)) {
            $this->flushDependenciesStack(0);
            ++$i;

            if ($i++ > 100) {
                throw new \RuntimeException(sprintf('%s error: Infinite loop detected in flush', self::class));
            }
        }
    }

    /**
     * @param AbstractUuidEntity[]|array[] $entities
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

    /**
     * @param AbstractUuidEntity[] $entities
     */
    private function appendDependencyEntities(string $class, array $entities, int $depth, array $currentBatch): void
    {
        foreach ($entities as $entity) {
            $this->addDependency($class, $entity->getId(), $depth, $currentBatch);
        }
    }

    private function appendDependencyIterator(string $class, iterable $iterator, int $depth, array $currentBatch): void
    {
        foreach ($iterator as $row) {
            $item = reset($row);
            $this->addDependency($class, $item['id'], $depth, $currentBatch);
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

    private function addDependency(string $class, string $id, int $depth, array $currentBatch): void
    {
        if ($this->isInBatch($class, $id, $currentBatch)) {
            return;
        }

        if (!isset($this->dependenciesStack[$class])) {
            $this->dependenciesStack[$class] = [];
        }
        if (!isset($this->dependenciesStack[$class][self::ACTION_UPSERT])) {
            $this->dependenciesStack[$class][self::ACTION_UPSERT] = [];
        }

        if (!in_array($id, $this->dependenciesStack[$class][self::ACTION_UPSERT], true)) {
            $this->dependenciesStack[$class][self::ACTION_UPSERT][] = $id;
            ++$this->dependenciesCount;

            if ($this->dependenciesCount >= self::MAX_PER_MESSAGE) {
                $this->flushDependenciesStack($depth);
            }
        }
    }

    private function flushDependenciesStack(int $depth): void
    {
        if (!empty($this->dependenciesStack)) {
            $objects = $this->dependenciesStack;
            $this->dependenciesStack = [];
            $this->dependenciesCount = 0;
            $this->scheduleIndex($objects, $depth + 1);
        }
    }
}
