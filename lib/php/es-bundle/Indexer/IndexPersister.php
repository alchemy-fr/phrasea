<?php

declare(strict_types=1);

namespace Alchemy\ESBundle\Indexer;

use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;

final readonly class IndexPersister
{
    /**
     * @param ObjectPersisterInterface[] $persisters
     */
    public function __construct(
        private array $persisters,
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
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->insertOne($object);
        }
    }

    public function replaceOne(string $class, $object): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->replaceOne($object);
        }
    }

    public function deleteOne(string $class, $object): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->deleteOne($object);
        }
    }

    public function deleteById(string $class, $id, $routing = false): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->deleteById($id, $routing);
        }
    }

    public function insertMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->insertMany($objects);
        }
    }

    public function replaceMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->replaceMany($objects);
        }
    }

    public function deleteMany(string $class, array $objects): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->deleteMany($objects);
        }
    }

    public function deleteManyByIdentifiers(string $class, array $identifiers, $routing = false): void
    {
        foreach ($this->persisters[$class] ?? [] as $persister) {
            $persister->deleteManyByIdentifiers($identifiers, $routing);
        }
    }
}
