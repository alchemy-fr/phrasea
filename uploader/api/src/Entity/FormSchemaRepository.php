<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class FormSchemaRepository extends EntityRepository
{
    public function getSchemaForLocale(string $targetId, ?string $locale): ?FormSchema
    {
        return $this->createQueryBuilder('fs')
            ->select('fs')
            ->andWhere('fs.target = :t')
            ->andWhere('fs.locale = :locale OR fs.locale IS NULL')
            ->setParameters([
                'locale' => $locale,
                't' => $targetId,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
