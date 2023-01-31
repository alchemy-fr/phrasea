<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Asset\Attribute\AttributesResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;
    private AttributeTypeRegistry $attributeTypeRegistry;
    private FieldNameResolver $fieldNameResolver;
    private CacheInterface $cache;
    private AttributesResolver $attributesResolver;
    private EntityManagerInterface $em;

    public function __construct(
        PermissionManager $permissionManager,
        AttributeTypeRegistry $attributeTypeRegistry,
        FieldNameResolver $fieldNameResolver,
        AttributesResolver $attributesResolver,
        EntityManagerInterface $em
    ) {
        $this->permissionManager = $permissionManager;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->disableCache();
        $this->attributesResolver = $attributesResolver;
        $this->em = $em;
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

            [$absolutePath, $cUsers, $cGroups] = $this->cache->get($collection->getId().'-'.$bestPrivacy, function () use ($collection, &$bestPrivacy): array {
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
                    $cGroups,
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
        $document->set('renditions', $this->compileRenditions($asset));
    }

    private function compileRenditions(Asset $asset): array
    {
        $renditionsDefinitions = $this->em->createQueryBuilder()
            ->select('rd.id')
            ->from(AssetRendition::class, 'r')
            ->innerJoin(RenditionDefinition::class, 'rd', Join::WITH, 'rd.id = r.definition')
            ->andWhere('r.asset = :id')
            ->setParameter('id', $asset->getId())
            ->getQuery()
            ->toIterable();

        $renditions = [];
        foreach ($renditionsDefinitions as $row) {
            $renditions[] = (string) $row['id'];
        }

        return $renditions;
    }

    private function compileAttributes(Asset $asset): array
    {
        $data = [];

        $attributes = $this->attributesResolver->resolveAttributes($asset, false);

        foreach ($attributes as $_attrs) {
            foreach ($_attrs as $l => $a) {
                $definition = $a->getDefinition();

                $type = $this->attributeTypeRegistry->getStrictType($definition->getFieldType());

                if ($definition->isMultiple()) {
                    $v = $a->getValues();
                    if (!empty($v)) {
                        $v = array_map(function (string $v) use ($type): string {
                            return $type->normalizeElasticsearchValue($v);
                        }, $v);
                    }
                } else {
                    $v = $a->getValue();
                    if (null !== $v) {
                        $v = $type->normalizeElasticsearchValue($v);
                    }
                }

                if (!empty($v)) {
                    $fieldName = $this->fieldNameResolver->getFieldName($definition);
                    $data[$l][$fieldName] = $v;
                }
            }
        }

        return [$data];
    }

    public static function getSubscribedEvents()
    {
        return [
            PostTransformEvent::class => 'hydrateDocument',
        ];
    }
}
