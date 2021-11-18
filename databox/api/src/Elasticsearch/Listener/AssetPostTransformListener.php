<?php

declare(strict_types=1);

namespace App\Elasticsearch\Listener;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Asset\Attribute\FallbackResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetPostTransformListener implements EventSubscriberInterface
{
    private PermissionManager $permissionManager;
    private EntityManagerInterface $em;
    private FallbackResolver $variableResolver;

    public function __construct(
        PermissionManager $permissionManager,
        EntityManagerInterface $em,
        FallbackResolver $variableResolver
    )
    {
        $this->permissionManager = $permissionManager;
        $this->em = $em;
        $this->variableResolver = $variableResolver;
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
        // Check ACE on asset

        if (null !== $asset->getOwnerId()) {
            $users[] = $asset->getOwnerId();
        }

        $collectionsPaths = [];
        foreach ($asset->getCollections() as $collectionAsset) {
            $collection = $collectionAsset->getCollection();

            if (($hierarchyBestPrivacy = $collection->getBestPrivacyInParentHierarchy()) > $bestPrivacy) {
                $bestPrivacy = $hierarchyBestPrivacy;
            }

            if ($bestPrivacy < WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS) {
                if (null !== $collection->getOwnerId()) {
                    $users[] = $collection->getOwnerId();
                }

                $users = array_merge($users, $this->permissionManager->getAllowedUsers($collection, PermissionInterface::VIEW));
                $groups = array_merge($groups, $this->permissionManager->getAllowedGroups($collection, PermissionInterface::VIEW));
            }

            $collectionsPaths[] = $collection->getAbsolutePath();
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

        $attributes = $this->em->getRepository(AttributeDefinition::class)
            ->createQueryBuilder('d')
            ->addSelect('d.name')
            ->addSelect('d.fallback')
            ->leftJoin(Attribute::class, 'a', Join::WITH, 'a.definition = d.id AND a.asset = :asset')
            ->addSelect('a.value')
            ->addSelect('a.locale')
            ->andWhere('d.workspace = :workspace')
            ->andWhere('a.value IS NOT NULL OR d.fallback IS NOT NULL')
            ->setParameter('asset', $asset->getId())
            ->setParameter('workspace', $asset->getWorkspaceId())
            ->getQuery()
            ->getResult();

        foreach ($attributes as $a) {
            if (!empty($a['value'])) {
                $data[$a['locale']][$a['name']] = $a['value'];
            }
        }
        foreach ($attributes as $a) {
            if ($a['fallback']) {
                foreach ($a['fallback'] as $locale => $fallback) {
                    if (!isset($data[$locale][$a['name']])) {
                        $data[$locale][$a['name']] = $this->resolveFallback($fallback, $asset);
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
