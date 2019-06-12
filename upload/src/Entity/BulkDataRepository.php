<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\ORM\EntityRepository;

class BulkDataRepository extends EntityRepository
{
    public function getBulkData(): ?BulkData
    {
        return $this->createQueryBuilder('bd')
            ->select('bd')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getBulkDataArray(): array
    {
        $bulkData = $this->getBulkData();

        return null !== $bulkData ? $bulkData->getData() : [];
    }

    public function persistBulkData(array $data): void
    {
        if (null === $bulkData = $this->getBulkData()) {
            $bulkData = new BulkData();
        }

        $bulkData->setData($data);
        $bulkData->setUpdatedAt(new DateTime());

        $this->_em->persist($bulkData);
        $this->_em->flush();
    }
}
