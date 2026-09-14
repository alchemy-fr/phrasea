<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\TermsSignature;
use App\Entity\Core\TermsVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TermsSignatureRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, TermsSignature::class);
    }

    public function findSignature(TermsVersion $termsVersion, string $userId): ?TermsSignature
    {
        return $this->findOneBy([
            'termsVersion' => $termsVersion->getId(),
            'userId' => $userId,
        ]);
    }
}
