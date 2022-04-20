<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Gedmo\Tool\Wrapper\EntityWrapper;

class EntitySerializer
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function convertToDatabaseValue(string $class, array $data): array
    {
        $newData = [];

        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => $value) {
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

    private function convertFieldToDatabaseValue(ClassMetadata $meta, string $field, $value)
    {
        if ($meta->isCollectionValuedAssociation($field)) {
            return null;
        } elseif ($meta->isSingleValuedAssociation($field)) {
            if (null !== $value) {
                $wrappedAssoc = new EntityWrapper($value, $this->em);
                return $wrappedAssoc->getIdentifier(false);
            } else {
                return null;
            }
        } else {
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
            return new ArrayCollection(); // TODO fix and set lazy
        } elseif ($meta->isSingleValuedAssociation($field)) {
            $mapping = $meta->getAssociationMapping($field);
            return $value ? $this->em->getReference($mapping['targetEntity'], $value) : null;
        } else {
            $type = Type::getType($meta->getTypeOfField($field));
            return $type->convertToPHPValue($value, $this->em->getConnection()->getDatabasePlatform());
        }
    }
}
