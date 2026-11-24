<?php

declare(strict_types=1);

namespace App\Repository\Core;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use App\Entity\Core\TermsSignature;
use App\Entity\Core\TermsVersion;
use App\Entity\Core\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;

class WorkspaceRepository extends ServiceEntityRepository
{
    private readonly CacheInterface $cache;

    public function __construct(
        ManagerRegistry $registry,
        TemporaryCacheFactory $cacheFactory,
    ) {
        parent::__construct($registry, Workspace::class);
        $this->cache = $cacheFactory->createCache();
    }

    /**
     * Public workspaces. With a user, those whose current Terms & Conditions
     * they have not signed are left out (see getReadableWorkspaceIds()).
     *
     * @return string[]
     */
    public function getPublicWorkspaceIds(?string $userId = null): array
    {
        return $this->cache->get(self::cacheKey('public', $userId), function () use ($userId): array {
            $queryBuilder = $this
                ->createQueryBuilder('t')
                ->select('t.id')
                ->andWhere('t.public = true')
                ->andWhere('t.deletedAt IS NULL');

            if (null !== $userId) {
                $this->excludeUnsignedTerms($queryBuilder, $userId);
            }

            return self::toIds($queryBuilder);
        });
    }

    /**
     * Workspaces whose content the user may read, as the WorkspaceVoter READ
     * attribute grants it: public, shared with the user (or their groups) or
     * owned by them, and — for an authenticated non-admin user — whose current
     * Terms & Conditions they have signed.
     *
     * A workspace whose terms are not signed is still listed by the workspace
     * endpoints (READ_NO_TERMS), so that the user can sign them.
     *
     * @return string[]
     */
    public function getReadableWorkspaceIds(?string $userId, array $groupIds, bool $isAdmin): array
    {
        sort($groupIds);

        return $this->cache->get(self::cacheKey('readable', $userId, $groupIds, $isAdmin), function () use ($userId, $groupIds, $isAdmin): array {
            $queryBuilder = $this
                ->createAllowedWorkspacesQueryBuilder($userId, $groupIds, $isAdmin)
                ->select('DISTINCT t.id');

            if (!$isAdmin && null !== $userId) {
                $this->excludeUnsignedTerms($queryBuilder, $userId);
            }

            return self::toIds($queryBuilder);
        });
    }

    private function createAllowedWorkspacesQueryBuilder(?string $userId, ?array $groupIds, bool $isAdmin): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL');

        if ($isAdmin) {
            return $queryBuilder;
        }

        if (null === $userId) {
            return $queryBuilder
                ->andWhere('t.public = true');
        }

        $queryBuilder->addGroupBy('t.id');
        AccessControlEntryRepository::joinAcl(
            $queryBuilder,
            $userId,
            $groupIds,
            Workspace::OBJECT_TYPE,
            't',
            PermissionInterface::VIEW,
            false,
        );

        $queryBuilder->andWhere('t.public = true OR ace.id IS NOT NULL OR t.ownerId = :uid');

        return $queryBuilder;
    }

    /**
     * Leaves out the workspaces whose current terms version (the highest one,
     * unless it is empty: see TermsManager::getCurrentTerms()) is not signed by the user.
     */
    private function excludeUnsignedTerms(QueryBuilder $queryBuilder, string $userId): void
    {
        $queryBuilder
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT tv.id FROM %1$s tv'
                .' WHERE tv.workspace = t.id'
                .' AND NOT EXISTS (SELECT tvn.id FROM %1$s tvn WHERE tvn.workspace = tv.workspace AND tvn.version > tv.version)'
                .' AND (tv.file IS NOT NULL OR TRIM(tv.text) <> \'\')'
                .' AND NOT EXISTS (SELECT ts.id FROM %2$s ts WHERE ts.termsVersion = tv.id AND ts.userId = :termsUid))',
                TermsVersion::class,
                TermsSignature::class,
            ))
            ->setParameter('termsUid', $userId);
    }

    /**
     * @return string[]
     */
    private static function toIds(QueryBuilder $queryBuilder): array
    {
        return array_map(fn (array $row): string => (string) $row['id'], $queryBuilder->getQuery()->getResult());
    }

    private static function cacheKey(mixed ...$args): string
    {
        return hash('xxh128', serialize($args));
    }
}
