<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TermsVersionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, TermsVersion::class);
    }

    public function getCurrentVersion(Workspace $workspace): ?TermsVersion
    {
        return $this->findOneBy([
            'workspace' => $workspace->getId(),
        ], [
            'version' => 'DESC',
        ]);
    }
}
