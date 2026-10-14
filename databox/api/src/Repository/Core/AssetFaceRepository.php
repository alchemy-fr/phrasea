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

    /**
     * The persons named in the workspaces: each identity with its number of faces,
     * and how many of them were named by a user (the others were recognized).
     *
     * @param string[] $workspaceIds
     *
     * @return list<array{identity: string, faces: int, userFaces: int}>
     */
    public function getIdentities(array $workspaceIds, ?string $query = null): array
    {
        $qb = $this->createWorkspacesQueryBuilder($workspaceIds)
            ->select('f.identity AS identity', 'COUNT(f.id) AS faces', 'SUM(CASE WHEN f.identityOrigin = :userOrigin THEN 1 ELSE 0 END) AS userFaces')
            ->andWhere('f.identity IS NOT NULL')
            ->setParameter('userOrigin', AssetFace::IDENTITY_ORIGIN_USER)
            ->groupBy('f.identity')
            ->orderBy('f.identity', 'ASC');

        if (null !== $query) {
            $qb
                ->andWhere('LOWER(f.identity) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        return array_map(static fn (array $row): array => [
            'identity' => (string) $row['identity'],
            'faces' => (int) $row['faces'],
            'userFaces' => (int) $row['userFaces'],
        ], $qb->getQuery()->getArrayResult());
    }

    /**
     * @param string[] $workspaceIds
     */
    public function countUnnamed(array $workspaceIds): int
    {
        return (int) $this->createWorkspacesQueryBuilder($workspaceIds)
            ->select('COUNT(f.id)')
            ->andWhere('f.identity IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Faces of the live assets of the given workspaces (aliases: "f" for the face, "a" for its asset).
     *
     * @param string[] $workspaceIds
     */
    public function createWorkspacesQueryBuilder(array $workspaceIds): QueryBuilder
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.asset', 'a')
            ->andWhere('a.workspace IN (:workspaces)')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('workspaces', $workspaceIds);
    }

    private function createWorkspaceQueryBuilder(string $workspaceId): QueryBuilder
    {
        return $this->createWorkspacesQueryBuilder([$workspaceId]);
    }
}
