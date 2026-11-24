<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use App\Entity\Core\Attribute;
use Elastica\Index;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * The "face" index is the index of the Attribute class, so every attribute indexed (ESIndex,
 * DeferredIndexListener) reaches its persister: only the faces (values carrying an embedding)
 * are kept. Deletions go through, a missing document being no error.
 *
 * Nothing is written before the index exists (populate): Elasticsearch would create it on the
 * first document, with a dynamic mapping (the embedding as plain floats, never searchable).
 */
#[AsDecorator(decorates: 'fos_elastica.object_persister.face')]
final class FaceIndexPersister implements ObjectPersisterInterface
{
    private bool $indexExists = false;

    public function __construct(
        #[AutowireDecorated]
        private readonly ObjectPersisterInterface $inner,
        #[Autowire(service: 'fos_elastica.index.face')]
        private readonly Index $index,
    ) {
    }

    public function handlesObject(object $object): bool
    {
        return self::isFace($object) && $this->inner->handlesObject($object);
    }

    public function insertOne(object $object): void
    {
        if (self::isFace($object) && $this->indexExists()) {
            $this->inner->insertOne($object);
        }
    }

    public function replaceOne(object $object): void
    {
        if (self::isFace($object) && $this->indexExists()) {
            $this->inner->replaceOne($object);
        }
    }

    public function deleteOne(object $object): void
    {
        if ($this->indexExists()) {
            $this->inner->deleteOne($object);
        }
    }

    public function deleteById(string $id, string|bool $routing = false): void
    {
        if ($this->indexExists()) {
            $this->inner->deleteById($id, $routing);
        }
    }

    public function insertMany(array $objects): void
    {
        $faces = self::filterFaces($objects);
        if (!empty($faces) && $this->indexExists()) {
            $this->inner->insertMany($faces);
        }
    }

    public function replaceMany(array $objects): void
    {
        $faces = self::filterFaces($objects);
        if (!empty($faces) && $this->indexExists()) {
            $this->inner->replaceMany($faces);
        }
    }

    public function deleteMany(array $objects): void
    {
        if (!empty($objects) && $this->indexExists()) {
            $this->inner->deleteMany($objects);
        }
    }

    public function deleteManyByIdentifiers(array $identifiers, string|bool $routing = false): void
    {
        if (!empty($identifiers) && $this->indexExists()) {
            $this->inner->deleteManyByIdentifiers($identifiers, $routing);
        }
    }

    /**
     * Only a missing index is checked again: once created, it is replaced through its alias.
     */
    public function indexExists(): bool
    {
        return $this->indexExists = $this->indexExists || $this->index->exists();
    }

    private static function isFace(object $object): bool
    {
        return $object instanceof Attribute && null !== $object->getEmbedding();
    }

    private static function filterFaces(array $objects): array
    {
        return array_values(array_filter($objects, self::isFace(...)));
    }
}
