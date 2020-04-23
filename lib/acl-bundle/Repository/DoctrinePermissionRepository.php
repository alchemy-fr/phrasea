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
                'object' => $this->getObjectUID($objectType, $objectId),
            ], [
                'createdAt' => 'DESC',
            ]);
    }

    public function getAce(string $userId, string $objectType, string $objectId): ?AccessControlEntryInterface
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectUID($objectType, $objectId),
                'userId' => $userId,
            ]);
    }

    public function updateOrCreateAce(string $userId, string $objectType, string $objectId, int $mask): ?AccessControlEntryInterface
    {
        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectUID($objectType, $objectId),
                'userId' => $userId,
            ]);


        if (!$ace instanceof AccessControlEntry) {
            $ace = new AccessControlEntry();
            $ace->setUserId($userId);
            $ace->setObject($this->getObjectUID($objectType, $objectId));
        }

        $ace->setMask($mask);

        $this->em->persist($ace);
        $this->em->flush();

        return $ace;
    }

    public function deleteAce(string $userId, string $objectType, string $objectId): void
    {
        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $this->getObjectUID($objectType, $objectId),
                'userId' => $userId,
            ]);

        if ($ace instanceof AccessControlEntry) {
            $this->em->remove($ace);
            $this->em->flush();
        }
    }

    private function getObjectUID(string $objectType, string $objectId): string
    {
        return $objectType . ':' . $objectId;
    }
}
