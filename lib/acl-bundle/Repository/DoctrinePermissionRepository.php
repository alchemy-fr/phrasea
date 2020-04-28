<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Repository;

use Alchemy\AclBundle\Entity\AccessControlEntry;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePermissionRepository implements PermissionRepositoryInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getObjectAces(string $objectType, string $objectId): array
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findBy([
                'object' => $this->getObjectURI($objectType, $objectId),
            ], [
                'createdAt' => 'DESC',
            ]);
    }

    public function getAce(string $entityType, string $entityId, string $objectType, string $objectId): ?AccessControlEntryInterface
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectURI($objectType, $objectId),
                'entityType' => AccessControlEntry::getEntityTypeFromString($entityType),
                'entityId' => $entityId,
            ]);
    }

    public function updateOrCreateAce(string $entityType, string $entityId, string $objectType, string $objectId, int $mask): ?AccessControlEntryInterface
    {
        $entityType = AccessControlEntry::getEntityTypeFromString($entityType);

        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectURI($objectType, $objectId),
                'entityType' => $entityType,
                'entityId' => $entityId,
            ]);

        if (!$ace instanceof AccessControlEntry) {
            $ace = new AccessControlEntry();
            $ace->setEntityType($entityType);
            $ace->setEntityId($entityId);
            $ace->setObject($this->getObjectURI($objectType, $objectId));
        }

        $ace->setMask($mask);

        $this->em->persist($ace);
        $this->em->flush();

        return $ace;
    }

    public function deleteAce(string $entityType, string $entityId, string $objectType, string $objectId): void
    {
        $entityType = AccessControlEntry::getEntityTypeFromString($entityType);

        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectURI($objectType, $objectId),
                'entityType' => $entityType,
                'entityId' => $entityId,
            ]);

        if ($ace instanceof AccessControlEntry) {
            $this->em->remove($ace);
            $this->em->flush();
        }
    }

    private function getObjectURI(string $objectType, string $objectId): string
    {
        return $objectType.':'.$objectId;
    }
}
