<?php

declare(strict_types=1);

namespace App\Repository\Core;

use App\Entity\Core\AssetFace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class AssetFaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetFace::class);
    }

    /**
     * @return AssetFace[]
     */
    public function findByAsset(string $assetId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.asset = :asset')
            ->setParameter('asset', $assetId)
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Faces identified by a user in the workspace: the reference gallery used for recognition.
     *
     * @return AssetFace[]
     */
    public function findReferenceFaces(string $workspaceId, ?string $excludeAssetId = null): array
    {
        $qb = $this->createWorkspaceQueryBuilder($workspaceId)
            ->andWhere('f.identity IS NOT NULL')
            ->andWhere('f.identityOrigin = :origin')
            ->setParameter('origin', AssetFace::IDENTITY_ORIGIN_USER);

        if (null !== $excludeAssetId) {
            $qb
                ->andWhere('f.asset != :excludedAsset')
                ->setParameter('excludedAsset', $excludeAssetId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Faces of the workspace that are not identified by a user (unknown or auto-identified),
     * i.e. the candidates for identity propagation.
     *
     * @return iterable<AssetFace>
     */
    public function iterateNonUserIdentifiedFaces(string $workspaceId, ?string $excludeAssetId = null): iterable
    {
        $qb = $this->createWorkspaceQueryBuilder($workspaceId)
            ->andWhere('(f.identity IS NULL OR f.identityOrigin != :origin)')
            ->setParameter('origin', AssetFace::IDENTITY_ORIGIN_USER);

        if (null !== $excludeAssetId) {
            $qb
                ->andWhere('f.asset != :excludedAsset')
                ->setParameter('excludedAsset', $excludeAssetId);
        }

        return $qb->getQuery()->toIterable();
    }

    /**
     * Faces whose identity was automatically derived from the given reference face.
     *
     * @return AssetFace[]
     */
    public function findDerivedFaces(AssetFace $reference): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.reference = :reference')
            ->setParameter('reference', $reference->getId())
            ->getQuery()
            ->getResult();
    }

    private function createWorkspaceQueryBuilder(string $workspaceId): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.asset', 'a')
            ->andWhere('a.workspace = :workspace')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('workspace', $workspaceId);
    }
}
