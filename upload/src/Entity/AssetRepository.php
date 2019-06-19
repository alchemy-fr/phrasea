<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\EntityRepository;

class AssetRepository extends EntityRepository
{
    public function attachFormDataAndToken(array $files, array $formData, string $token): void
    {
        $this->createQueryBuilder('a')
            ->update(Asset::class, 'a')
            ->set('a.formData', ':data')
            ->set('a.token', ':token')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('data', json_encode($formData))
            ->setParameter('token', $token)
            ->setParameter('ids', $files)
            ->getQuery()
            ->execute();
    }
}
