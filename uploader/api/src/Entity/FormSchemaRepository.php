<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class FormSchemaRepository extends EntityRepository
{
    public function getSchemaForLocale(string $targetId, ?string $locale): ?FormSchema
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->addSelect('CASE WHEN t.locale = :locale THEN 2 WHEN t.locale IS NULL THEN 1 ELSE 0 END AS HIDDEN locale_match')
            ->andWhere('t.target = :t')
            ->setParameters([
                'locale' => $locale,
                't' => $targetId,
            ])
            ->orderBy('locale_match', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
