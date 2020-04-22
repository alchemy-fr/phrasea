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

    public function getObjectAces(string $objectId): array
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findBy([
                'object' => $objectId,
            ]);
    }

    public function getAce(string $userId, string $objectId): ?AccessControlEntryInterface
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'object' => $objectId,
                'userId' => $userId,
            ]);
    }
}
