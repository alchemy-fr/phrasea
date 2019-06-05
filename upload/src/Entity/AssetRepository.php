<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    public function attachFormData(array $files, array $formData): void
    {
        $this->createQueryBuilder('a')
            ->update(Asset::class, 'a')
            ->set('a.formData', ':data')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('data', json_encode($formData))
            ->setParameter('ids', $files)
            ->getQuery()
            ->execute();
    }
}
