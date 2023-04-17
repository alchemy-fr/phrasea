<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AttributeDefinitionRepositoryMemoryCachedDecorator extends EntityRepository implements AttributeDefinitionRepositoryInterface, CacheRepositoryInterface
{
    use CacheDecoratorTrait;

    public const LIST_TAG = 'attr_def_list';

    private TagAwareCacheInterface $cache;

    public function __construct(ManagerRegistry $registry, AttributeDefinitionRepositoryInterface $decorated, TagAwareCacheInterface $memoryCache)
    {
        $this->decorated = $decorated;
        $this->cache = $memoryCache;

        $manager = $registry->getManagerForClass(AttributeDefinition::class);

        parent::__construct($manager, $manager->getClassMetadata(AttributeDefinition::class));
    }

    public function getSearchableAttributes(
        ?string $userId,
        array   $groupIds,
        array   $options = []
    ): array
    {
        return $this->decorated->getSearchableAttributes($userId, $groupIds, $options);
    }

    public function findByKey(string $key, string $workspaceId): ?AttributeDefinition
    {
        return $this->decorated->findByKey($key, $workspaceId);
    }

    public function getWorkspaceFallbackDefinitions(string $workspaceId): array
    {
        $wasCached = true;
        $definitions = $this->cache->get(
            sprintf('attr_def_fb_%s', $workspaceId),
            function (ItemInterface $item) use ($workspaceId, &$wasCached) {
                $wasCached = false;
                $item->tag(self::LIST_TAG);

                /** @var AttributeDefinitionRepository $decorated */
                $decorated = $this->decorated;

                return $decorated->getWorkspaceFallbackDefinitions($workspaceId);
            }
        );

        return $wasCached ? $this->mergeEntities($definitions) : $definitions;
    }

    public function getWorkspaceInitializeDefinitions(string $workspaceId): array
    {
        $wasCached = true;
        $definitions = $this->cache->get(
            sprintf('attr_def_ini_%s', $workspaceId),
            function (ItemInterface $item) use ($workspaceId, &$wasCached) {
                $wasCached = false;
                $item->tag(self::LIST_TAG);

                /** @var AttributeDefinitionRepository $decorated */
                $decorated = $this->decorated;

                return $decorated->getWorkspaceInitializeDefinitions($workspaceId);
            }
        );

        return $wasCached ? $this->mergeEntities($definitions) : $definitions;
    }

    public function getWorkspaceDefinitions(string $workspaceId): array
    {
        $wasCached = true;
        $definitions = $this->cache->get(
            sprintf('attr_defs_%s', $workspaceId),
            function (ItemInterface $item) use ($workspaceId, &$wasCached) {
                $wasCached = false;
                $item->tag(self::LIST_TAG);

                /** @var AttributeDefinitionRepository $decorated */
                $decorated = $this->decorated;

                return $decorated->getWorkspaceDefinitions($workspaceId);
            }
        );

        return $wasCached ? $this->mergeEntities($definitions) : $definitions;
    }

    public function invalidateEntity(string $id): void
    {
    }

    public function invalidateList(): void
    {
        $this->cache->invalidateTags([
            self::LIST_TAG,
        ]);
    }
}
