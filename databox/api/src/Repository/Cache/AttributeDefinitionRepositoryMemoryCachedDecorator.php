<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AttributeDefinitionRepositoryMemoryCachedDecorator implements AttributeDefinitionRepositoryInterface, CacheRepositoryInterface
{
    use CacheDecoratorTrait;

    public const LIST_TAG = 'attr_def_list';

    private TagAwareCacheInterface $cache;

    public function __construct(AttributeDefinitionRepositoryInterface $decorated, TagAwareCacheInterface $memoryCache)
    {
        $this->decorated = $decorated;
        $this->cache = $memoryCache;
    }

    public function getSearchableAttributes(
        ?array $workspaceIds,
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        return $this->decorated->getSearchableAttributes($workspaceIds, $userId, $groupIds, $options);
    }

    public function findByKey(string $key, string $workspaceId): ?AttributeDefinition
    {
        return $this->decorated->findByKey($key, $workspaceId);
    }

    public function getWorkspaceFallbackDefinitions(string $workspaceId): array
    {
        return $this->cache->get(sprintf('attr_def_fb_%s', $workspaceId), function (ItemInterface $item) use ($workspaceId) {
            $item->tag(self::LIST_TAG);

            return $this->decorated->getWorkspaceFallbackDefinitions($workspaceId);
        });
    }

    public function getWorkspaceDefinitions(string $workspaceId): array
    {
        return $this->cache->get(sprintf('attr_defs_%s', $workspaceId), function (ItemInterface $item) use ($workspaceId) {
            $item->tag(self::LIST_TAG);

            return $this->decorated->getWorkspaceDefinitions($workspaceId);
        });
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
