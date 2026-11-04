<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Api\Model\Input\CollectionInput;
use App\Automation\UserContextSwitcher;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves the "path" of an entry ("Sport/Football") to a collection, from a base collection
 * or the workspace root: each missing collection is created as with POST /collections, as
 * the import initiator.
 *
 * Paths are resolved by the single process that reads the file, so that two entries never
 * create the same collection twice.
 */
final readonly class CollectionPathResolver
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserContextSwitcher $userContextSwitcher,
        private ApiInputPersister $apiInputPersister,
    ) {
    }

    /**
     * @param string[]              $segments the collection names, from the base
     * @param array<string, string> $cache    collection ID by path, kept by the caller for the whole import
     */
    public function resolve(
        string $workspaceId,
        ?string $baseCollectionId,
        array $segments,
        string $initiatorId,
        array &$cache,
    ): string {
        $parentId = $baseCollectionId;
        $path = '';
        foreach ($segments as $name) {
            $path .= '/'.$name;
            if (!isset($cache[$path])) {
                $cache[$path] = $this->findChild($workspaceId, $parentId, $name)
                    ?? $this->createChild($workspaceId, $parentId, $name, $initiatorId);
            }

            $parentId = $cache[$path];
        }

        return $parentId;
    }

    private function findChild(string $workspaceId, ?string $parentId, string $name): ?string
    {
        $queryBuilder = $this->em->getRepository(Collection::class)
            ->createQueryBuilder('c')
            ->select('c.id')
            ->andWhere('c.workspace = :ws')
            ->andWhere('c.name = :name')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.storyAsset IS NULL')
            ->setParameter('ws', $workspaceId)
            ->setParameter('name', $name)
            ->addOrderBy('c.createdAt', 'ASC')
            ->setMaxResults(1);

        if (null !== $parentId) {
            $queryBuilder
                ->andWhere('c.parent = :parent')
                ->setParameter('parent', $parentId);
        } else {
            $queryBuilder->andWhere('c.parent IS NULL');
        }

        $row = $queryBuilder->getQuery()->getOneOrNullResult();

        return null !== $row ? (string) $row['id'] : null;
    }

    private function createChild(string $workspaceId, ?string $parentId, string $name, string $initiatorId): string
    {
        return $this->userContextSwitcher->runAs($initiatorId, function () use ($workspaceId, $parentId, $name): string {
            $input = new CollectionInput();
            $input->name = $name;
            if (null !== $parentId) {
                $input->parent = $this->em->find(Collection::class, $parentId);
            } else {
                $input->workspace = $this->em->find(Workspace::class, $workspaceId);
            }

            return $this->apiInputPersister->create(Collection::class, $input)->getId();
        });
    }
}
