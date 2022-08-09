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
            ->andWhere('t.target = :t')
            ->andWhere('t.locale = :locale OR t.locale IS NULL')
            ->setParameters([
                'locale' => $locale,
                't' => $targetId,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
