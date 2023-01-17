<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\ChuckNorrisVoter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;

class AttributeDefinitionRepository extends ServiceEntityRepository implements AttributeDefinitionRepositoryInterface
{
    private Security $security;

    public function __construct(ManagerRegistry $registry, Security $security)
    {
        parent::__construct($registry, AttributeDefinition::class);
        $this->security = $security;
    }

    /**
     * @return AttributeDefinition[]
     */
    public function getSearchableAttributes(?array $workspaceIds, ?string $userId, array $groupIds, array $options = []): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('t')
            ->andWhere('t.searchable = true')
            ->innerJoin('t.class', 'c')
        ;

        if (!$this->security->isGranted(ChuckNorrisVoter::ROLE)) {
            if (null !== $userId) {
                AccessControlEntryRepository::joinAcl(
                    $queryBuilder,
                    $userId,
                    $groupIds,
                    'attribute_class',
                    'c',
                    PermissionInterface::VIEW,
                    false
                );
                $queryBuilder->andWhere('c.public = true OR ace.id IS NOT NULL');
            } else {
                $queryBuilder->andWhere('c.public = true');
            }
        }

        if (null !== $workspaceIds) {
            $queryBuilder
                ->andWhere('t.workspace IN (:w)')
                ->setParameter('w', $workspaceIds)
            ;
        }

        if ($options[self::OPT_TYPES] ?? null) {
            $queryBuilder
                ->andWhere('t.fieldType IN (:types)')
                ->setParameter('types', $options[self::OPT_TYPES]);
        }

        if ($options[self::OPT_FACET_ENABLED] ?? null) {
            $queryBuilder
                ->andWhere('t.facetEnabled = :fc')
                ->setParameter('fc', $options[self::OPT_FACET_ENABLED]);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function findByKey(string $key, string $workspaceId): ?AttributeDefinition
    {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->andWhere('t.key = :key')
            ->andWhere('t.workspace = :ws')
            ->setParameter('key', $key)
            ->setParameter('ws', $workspaceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getWorkspaceFallbackDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.fallback IS NOT NULL')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }

    public function getWorkspaceDefinitions(string $workspaceId): array
    {
        return $this
            ->createQueryBuilder('d')
            ->andWhere('d.workspace = :workspace')
            ->setParameter('workspace', $workspaceId)
            ->getQuery()
            ->getResult();
    }
}
