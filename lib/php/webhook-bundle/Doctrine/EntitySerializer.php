<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Gedmo\Tool\Wrapper\EntityWrapper;

class EntitySerializer
{
    private const MAX_COLLECTION_COUNT = 100;
    private readonly EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function convertToDatabaseValue(string $class, array $data): array
    {
        $newData = [];

        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => $value) {
            if (!$meta->hasField($field) && !$meta->hasAssociation($field)) {
                continue;
            }
            $newData[$field] = $this->convertFieldToDatabaseValue($meta, $field, $value);
        }

        return $newData;
    }

    public function convertChangeSetToDatabaseValue(string $class, array $data): array
    {
        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => &$values) {
            foreach ($values as &$value) {
                $value = $this->convertFieldToDatabaseValue($meta, $field, $value);
            }
        }

        return $data;
    }

    public function getEntityIdentifier(object $entity)
    {
        $wrappedAssoc = new EntityWrapper($entity, $this->em);

        return $wrappedAssoc->getIdentifier(false);
    }

    private function convertFieldToDatabaseValue(ClassMetadata $meta, string $field, $value)
    {
        $assocType = $meta->hasAssociation($field) ? $meta->getAssociationMapping($field)['type'] : null;

        switch ($assocType) {
            case ClassMetadata::MANY_TO_ONE:
                if (null !== $value) {
                    return $this->getEntityIdentifier($value);
                }

                return null;
            case ClassMetadata::MANY_TO_MANY:
                if ($value instanceof Collection) {
                    if ($value->count() > self::MAX_COLLECTION_COUNT) {
                        return null;
                    }

                    return $value->map(fn (object $object) => $this->getEntityIdentifier($object))->toArray();
                }

                return null;
            default:
            case ClassMetadata::ONE_TO_MANY:
                return null;
            case null:
                $type = Type::getType($meta->getTypeOfField($field));

                return $type->convertToDatabaseValue($value, $this->em->getConnection()->getDatabasePlatform());
        }
    }

    public function convertToPhpValue(string $class, array $data): array
    {
        $newData = [];

        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => $value) {
            $newData[$field] = $this->convertFieldToPhpValue($meta, $field, $value);
        }

        return $newData;
    }

    public function convertChangeSetToPhpValue(string $class, array $data): array
    {
        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => &$values) {
            foreach ($values as &$value) {
                $value = $this->convertFieldToPhpValue($meta, $field, $value);
            }
        }

        return $data;
    }

    private function convertFieldToPhpValue(ClassMetadata $meta, string $field, $value)
    {
        if ($meta->isCollectionValuedAssociation($field)) {
            if (null === $value) {
                return null;
            }

            $target = $meta->getAssociationTargetClass($field);
            $targetMeta = $this->em->getClassMetadata($target);
            $collection = new PersistentCollection($this->em, $targetMeta, new ArrayCollection(array_map(fn ($id): object => $this->em->getReference($target, $id), $value)));
            $collection->takeSnapshot();

            return $collection;
        } elseif ($meta->isSingleValuedAssociation($field)) {
            $mapping = $meta->getAssociationMapping($field);

            return $value ? $this->em->getReference($mapping['targetEntity'], $value) : null;
        } else {
            $type = Type::getType($meta->getTypeOfField($field));

            return $type->convertToPHPValue($value, $this->em->getConnection()->getDatabasePlatform());
        }
    }
}
