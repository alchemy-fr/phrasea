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

    public function getObjectAces(string $objectType, ?string $objectId): array
    {
        return $this->em->getRepository(AccessControlEntry::class)
            ->findBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
            ], [
                'createdAt' => 'DESC',
            ]);
    }

    public function getAces(string $userId, array $groupIds, string $objectType, string $objectId): array
    {
        return $this->em
            ->getRepository(AccessControlEntry::class)
            ->findAces($userId, $groupIds, $objectType, $objectId);
    }

    public function updateOrCreateAce(string $userType, ?string $userId, string $objectType, ?string $objectId, int $mask): ?AccessControlEntryInterface
    {
        $userId = $userId === AccessControlEntry::USER_WILDCARD ? null : $userId;
        $userType = AccessControlEntry::getUserTypeFromString($userType);

        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => $userType,
                'userId' => $userId,
            ]);

        if (!$ace instanceof AccessControlEntry) {
            $ace = new AccessControlEntry();
            $ace->setUserType($userType);
            $ace->setUserId($userId);
            $ace->setObjectType($objectType);
            $ace->setObjectId($objectId);
        }

        $ace->setMask($mask);

        $this->em->persist($ace);
        $this->em->flush();

        return $ace;
    }

    public function deleteAce(string $userType, ?string $userId, string $objectType, ?string $objectId): void
    {
        $userId = $userId === AccessControlEntry::USER_WILDCARD ? null : $userId;
        $userType = AccessControlEntry::getUserTypeFromString($userType);

        $ace = $this->em->getRepository(AccessControlEntry::class)
            ->findOneBy([
                'objectType' => $objectType,
                'objectId' => $objectId,
                'userType' => $userType,
                'userId' => $userId,
            ]);

        if ($ace instanceof AccessControlEntry) {
            $this->em->remove($ace);
            $this->em->flush();
        }
    }
}
