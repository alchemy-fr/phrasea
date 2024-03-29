<?php

declare(strict_types=1);

namespace App\Repository\Cache;

use App\Entity\Core\AttributeDefinition;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AttributeDefinitionRepositoryMemoryCachedDecorator extends EntityRepository implements AttributeDefinitionRepositoryInterface, CacheRepositoryInterface
{
    use CacheDecoratorTrait;

    final public const LIST_TAG = 'attr_def_list';

    public function __construct(ManagerRegistry $registry, AttributeDefinitionRepositoryInterface $decorated, private readonly TagAwareCacheInterface $cache)
    {
        $this->decorated = $decorated;

        $manager = $registry->getManagerForClass(AttributeDefinition::class);

        parent::__construct($manager, $manager->getClassMetadata(AttributeDefinition::class));
    }

    public function getSearchableAttributes(
        ?string $userId,
        array $groupIds,
        array $options = []
    ): array {
        return $this->decorated->getSearchableAttributes($userId, $groupIds, $options);
    }

    public function getSearchableAttributesWithPermission(
        ?string $userId,
        array $groupIds
    ): iterable {
        return $this->decorated->getSearchableAttributesWithPermission($userId, $groupIds);
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

    public function getWorkspaceInitializeDefinitions(string $workspaceId): array
    {
        return $this->cache->get(sprintf('attr_def_ini_%s', $workspaceId), function (ItemInterface $item) use ($workspaceId) {
            $item->tag(self::LIST_TAG);

            return $this->decorated->getWorkspaceInitializeDefinitions($workspaceId);
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
