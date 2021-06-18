<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\EntityRepository;

class CommitRepository extends EntityRepository
{
    /**
     * @return Commit[]
     */
    public function getAcknowledgedBefore(DateTime $date): array
    {
        return $this
            ->createQueryBuilder('c')
            ->select('c')
            ->andWhere('c.acknowledged = true')
            ->andWhere('c.acknowledgedAt < :date')
            ->setParameter('date', $date->format('Y-m-d H:i:s'))
            ->getQuery()
            ->getResult();
    }
}
