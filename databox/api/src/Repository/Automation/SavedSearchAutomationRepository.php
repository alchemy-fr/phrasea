<?php

declare(strict_types=1);

namespace App\Repository\Automation;

use App\Entity\Automation\SavedSearchAutomation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedSearchAutomation>
 */
class SavedSearchAutomationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedSearchAutomation::class);
    }

    /**
     * The enabled scheduled automations whose next run is due.
     *
     * @return SavedSearchAutomation[]
     */
    public function findDue(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.enabled = true')
            ->andWhere('t.schedule IS NOT NULL')
            ->andWhere('t.nextRunAt IS NOT NULL')
            ->andWhere('t.nextRunAt <= :now')
            ->setParameter('now', $now)
            ->addOrderBy('t.nextRunAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SavedSearchAutomation[]
     */
    public function findBySavedSearch(string $savedSearchId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.savedSearch = :ss')
            ->setParameter('ss', $savedSearchId)
            ->addOrderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function disableBySavedSearch(string $savedSearchId): int
    {
        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.enabled', ':false')
            ->set('t.nextRunAt', 'NULL')
            ->andWhere('t.savedSearch = :ss')
            ->andWhere('t.enabled = true')
            ->setParameter('false', false)
            ->setParameter('ss', $savedSearchId)
            ->getQuery()
            ->execute();
    }
}
