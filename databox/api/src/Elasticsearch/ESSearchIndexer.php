<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Consumer\Handler\Search\SearchIndexHandler;
use App\Entity\AbstractUuidEntity;
use App\Entity\Core\Asset;
use App\Entity\Core\Collection;
use App\Entity\SearchDependencyInterface;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;

class ESSearchIndexer
{
    const MAX_DEPTH = 10;

    const ACTION_INSERT = 'i';
    const ACTION_UPSERT = 'u';
    const ACTION_DELETE = 'd';

    /**
     * @var ObjectPersisterInterface[][]
     */
    protected array $objectPersisters = [];
    private EventProducer $eventProducer;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private bool $direct;

    public function __construct(EventProducer $eventProducer, EntityManagerInterface $em, LoggerInterface $logger, bool $direct = false)
    {
        $this->eventProducer = $eventProducer;
        $this->em = $em;
        $this->logger = $logger;
        $this->direct = $direct;
    }

    public function addObjectPersister(string $class, ObjectPersisterInterface $objectPersister): void
    {
        if (!isset($this->objectPersisters[$class])) {
            $this->objectPersisters[$class] = [];
        }
        $this->objectPersisters[$class][] = $objectPersister;
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

    public function index(array $objects, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            $this->logger->emergency(sprintf('%s: Max depth reached', __CLASS__));

            return;
        }

        foreach ($objects as $class => $entities) {
            foreach ($entities as $operation => $ids) {
                $this->indexClass($class, $ids, $operation, $depth);
            }
        }
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

    private function indexClass(string $class, array $ids, string $operation, int $depth): void
    {
        $class = ClassUtils::getRealClass($class);
        $persisters = $this->objectPersisters[$class] ?? [];

        $ids = array_unique($ids);

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

                if (count($objects) !== count($ids)) {
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
                        $this->updateDependencies($object, $depth);
                    }
                }

                break;
        }
    }

    private function updateDependencies(SearchDependencyInterface $object, int $depth): void
    {
        $entities = [];

        if ($object instanceof Collection) {
            $entities = array_merge($entities, $this->em->getRepository(Asset::class)
                ->getCollectionAssets($object->getId()));
        }

        $objects = [];
        self::computeObjects($objects, $entities, self::ACTION_UPSERT);

        if (!empty($objects)) {
            $this->scheduleIndex($objects, $depth + 1);
        }
    }

    /**
     * @param AbstractUuidEntity[] $entities
     */
    public static function computeObjects(array &$objects, array $entities, string $operation): void
    {
        foreach ($entities as $entity) {
            $class = ClassUtils::getRealClass(get_class($entity));

            if (!isset($objects[$class])) {
                $objects[$class] = [];
            }
            if (!isset($objects[$class][$operation])) {
                $objects[$class][$operation] = [];
            }
            $objects[$class][$operation][] = $entity->getId();
        }
    }
}
