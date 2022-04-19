<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Doctrine;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
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
            if ($meta->isCollectionValuedAssociation($field)) {
                $transformed = null;
            } elseif ($meta->isSingleValuedAssociation($field)) {
                if (null !== $value) {
                    $wrappedAssoc = new EntityWrapper($value, $this->em);
                    $transformed = $wrappedAssoc->getIdentifier(false);
                } else {
                    $transformed = null;
                }
            } else {
                $type = Type::getType($meta->getTypeOfField($field));
                $transformed = $type->convertToDatabaseValue($value, $this->em->getConnection()->getDatabasePlatform());

            }
            $newData[$field] = $transformed;
        }

        return $newData;
    }

    public function convertToPhpValue(string $class, array $data): array
    {
        $newData = [];

        $meta = $this->em->getClassMetadata($class);
        foreach ($data as $field => $value) {
            if ($meta->isCollectionValuedAssociation($field)) {
                $transformed = new ArrayCollection(); // TODO fix and set lazy
            } elseif ($meta->isSingleValuedAssociation($field)) {
                $mapping = $meta->getAssociationMapping($field);
                $transformed = $value ? $this->em->getReference($mapping['targetEntity'], $value) : null;
            } else {
                $type = Type::getType($meta->getTypeOfField($field));
                $transformed = $type->convertToPHPValue($value, $this->em->getConnection()->getDatabasePlatform());
            }
            $newData[$field] = $transformed;
        }

        return $newData;
    }
}
