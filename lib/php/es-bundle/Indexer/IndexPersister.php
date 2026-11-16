<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

use Elastica\Exception\Bulk\Response\ActionException;
use Elastica\Exception\Bulk\ResponseException as BulkResponseException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class IndexPersister
{
    /**
     * @param array<string, array<string|int, ObjectPersisterInterface>> $persisters class => [logical index name => persister]
     */
    public function __construct(
        private array $persisters,
        private LoggerInterface $logger = new NullLogger(),
        private ?IndexWriteJournalInterface $writeJournal = null,
    ) {
    }

    public function getPersisters(): array
    {
        return $this->persisters;
    }

    public function hasObjectPersisterFor(string $class): bool
    {
        return !empty($this->persisters[$class]);
    }

    public function insertOne(string $class, $object): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, [$object]);
            $persister->insertOne($object);
        }
    }

    public function replaceOne(string $class, $object): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, [$object]);
            $persister->replaceOne($object);
        }
    }

    public function deleteOne(string $class, $object): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, [$object]);
            $persister->deleteOne($object);
        }
    }

    public function deleteById(string $class, $id, $routing = false): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->recordIds($indexName, [$id]);
            $persister->deleteById($id, $routing);
        }
    }

    public function insertMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, $objects);
            $persister->insertMany($objects);
        }
    }

    public function replaceMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, $objects);
            try {
                $persister->replaceMany($objects);
            } catch (BulkResponseException $e) {
                // replaceMany is an upsert: when two workers index the same document
                // concurrently, ES rejects the loser with a version conflict although
                // the document now holds the same (fresh) data. Nothing to retry.
                if (!$this->onlyVersionConflicts($e)) {
                    throw $e;
                }

                $this->logger->info('Ignoring version conflicts while replacing documents', [
                    'class' => $class,
                    'errors' => $e->getFailures(),
                ]);
            }
        }
    }

    private function onlyVersionConflicts(BulkResponseException $e): bool
    {
        $actionExceptions = $e->getActionExceptions();
        if (empty($actionExceptions)) {
            return false;
        }

        foreach ($actionExceptions as $actionException) {
            if (!$this->isVersionConflict($actionException)) {
                return false;
            }
        }

        return true;
    }

    private function isVersionConflict(ActionException $actionException): bool
    {
        $response = $actionException->getResponse();
        $data = $response->getData();
        if (409 === ($data['status'] ?? null)) {
            return true;
        }

        $error = $response->getFullError();
        $type = is_array($error) ? ($error['type'] ?? $error['root_cause'][0]['type'] ?? null) : null;

        return 'version_conflict_engine_exception' === $type;
    }

    public function deleteMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->record($indexName, $objects);
            $persister->deleteMany($objects);
        }
    }

    public function deleteManyByIdentifiers(string $class, array $identifiers, $routing = false): void
    {
        foreach ($this->persisters[$class] ?? [] as $indexName => $persister) {
            $this->recordIds($indexName, $identifiers);
            $persister->deleteManyByIdentifiers($identifiers, $routing);
        }
    }

    private function record(string|int $indexName, array $objects): void
    {
        if (null === $this->writeJournal) {
            return;
        }

        $ids = [];
        foreach ($objects as $object) {
            if (\is_object($object) && method_exists($object, 'getId')) {
                $ids[] = $object->getId();
            }
        }
        $this->recordIds($indexName, $ids);
    }

    /**
     * Persisters registered without their index name (custom persister service) are not journaled.
     */
    private function recordIds(string|int $indexName, array $ids): void
    {
        if (null === $this->writeJournal || !\is_string($indexName)) {
            return;
        }

        $ids = array_values(array_filter(array_map(strval(...), $ids), static fn (string $id): bool => '' !== $id));
        if ([] !== $ids) {
            $this->writeJournal->recordDocuments($indexName, $ids);
        }
    }
}
