<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Asset\Attribute\FallbackResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;
    private EntityManagerInterface $em;
    private FallbackResolver $variableResolver;
    private AttributeTypeRegistry $attributeTypeRegistry;
    private FieldNameResolver $fieldNameResolver;
    private CacheInterface $cache;

    public function __construct(
        PermissionManager $permissionManager,
        EntityManagerInterface $em,
        FallbackResolver $variableResolver,
        AttributeTypeRegistry $attributeTypeRegistry,
        FieldNameResolver $fieldNameResolver
    ) {
        $this->permissionManager = $permissionManager;
        $this->em = $em;
        $this->variableResolver = $variableResolver;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->disableCache();
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    public function disableCache(): void
    {
        $this->cache = new NullAdapter();
    }

    public function hydrateDocument(PostTransformEvent $event): void
    {
        /** @var Asset $asset */
        if (!($asset = $event->getObject()) instanceof Asset) {
            return;
        }

        $document = $event->getDocument();

        $bestPrivacy = $asset->getPrivacy();

        $users = $this->permissionManager->getAllowedUsers($asset, PermissionInterface::VIEW);
        $groups = $this->permissionManager->getAllowedGroups($asset, PermissionInterface::VIEW);

        if (null !== $asset->getOwnerId()) {
            $users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();

            [$absolutePath, $cUsers, $cGroups] = $this->cache->get($collection->getId(), function () use ($collection, &$bestPrivacy): array {
                if (($hierarchyBestPrivacy = $collection->getBestPrivacyInParentHierarchy()) > $bestPrivacy) {
                    $bestPrivacy = $hierarchyBestPrivacy;
                }

                $cUsers = [];
                $cGroups = [];

                if ($bestPrivacy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                    if (null !== $collection->getOwnerId()) {
                        $cUsers[] = $collection->getOwnerId();
                    }

                    $cUsers = array_merge($cUsers, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
                    $cGroups = array_merge($cGroups, $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW));
                }

                $absPath = $collection->getAbsolutePath();

                return [
                    $absPath,
                    $cUsers,
                    $cGroups
                ];
            });

            $collectionsPaths[] = $absolutePath;
            $users = array_merge($users, $cUsers);
            $groups = array_merge($groups, $cGroups);
        }

        $document->set('privacy', $bestPrivacy);
        $document->set('users', array_values(array_unique($users)));
        $document->set('groups', array_values(array_unique($groups)));
        $document->set('collectionPaths', array_unique($collectionsPaths));
        $document->set('attributes', $this->compileAttributes($asset));
    }

    private function compileAttributes(Asset $asset): array
    {
        $data = [];

        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.definition', 'd')
            ->andWhere('d.workspace = :workspace')
            ->andWhere('d.searchable = true')
            ->andWhere('a.asset = :asset')
            ->setParameter('asset', $asset->getId())
            ->setParameter('workspace', $asset->getWorkspaceId())
            ->getQuery()
            ->getResult();

        foreach ($attributes as $a) {
            $definition = $a->getDefinition();
            $v = $a->getValue();
            $fieldName = $this->fieldNameResolver->getFieldName($definition);
            $l = $a->getLocale() ?? IndexMappingUpdater::NO_LOCALE;

            if (null !== $v) {
                $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());
                $v = $type->normalizeValue($v);
            }

            if (!empty($v)) {
                if ($definition->isMultiple()) {
                    if (!isset($data[$l][$fieldName])) {
                        $data[$l][$fieldName] = [];
                    }
                    $data[$l][$fieldName][] = $v;
                } else {
                    $data[$l][$fieldName] = $v;
                }
            }
        }

        $workspaceId = $asset->getWorkspaceId();
        /** @var AttributeDefinition[] $definitions */
        $definitions = $this->cache->get(sprintf('def_%s', $workspaceId), function () use ($workspaceId): array {
            return $this->em->getRepository(AttributeDefinition::class)
                    ->createQueryBuilder('d')
                    ->andWhere('d.fallback IS NOT NULL')
                    ->andWhere('d.workspace = :workspace')
                    ->setParameter('workspace', $workspaceId)
                    ->getQuery()
                    ->getResult();
        });

        foreach ($definitions as $definition) {
            $fieldName = $this->fieldNameResolver->getFieldName($definition);

            if (null !== $definition->getFallback()) {
                foreach ($definition->getFallback() as $locale => $fallback) {
                    if (!isset($data[$locale][$fieldName])) {
                        $fallbackValue = $this->resolveFallback($fallback, $asset);
                        if ($definition->isMultiple()) {
                            $fallbackValue = [$fallbackValue];
                        }
                        $data[$locale][$fieldName] = $fallbackValue;
                    }
                }
            }
        }

        return [$data];
    }

    private function resolveFallback(string $fallback, Asset $asset): string
    {
        return $this->variableResolver->resolveFallback($fallback, [
            'file' => $asset->getFile(),
            'asset' => $asset,
        ]);
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }

}
