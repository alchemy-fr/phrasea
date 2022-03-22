<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Api\Model\Output\AssetOutput;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\File;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;
    private RenditionPermissionManager $renditionPermissionManager;
    private FieldNameResolver $fieldNameResolver;

    public function __construct(
        EntityManagerInterface $em,
        RenditionPermissionManager $renditionPermissionManager,
        FieldNameResolver $fieldNameResolver
    ) {
        $this->em = $em;
        $this->renditionPermissionManager = $renditionPermissionManager;
        $this->fieldNameResolver = $fieldNameResolver;
    }

    /**
     * @param Asset $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $user = $this->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());

        $highlights = $object->getElasticHighlights();
        $output->setTitle($object->getTitle());
        if (!empty($highlights) && isset($highlights['title'])) {
            $output->setTitleHighlight(reset($highlights['title']));
        }

        $output->setPrivacy($object->getPrivacy());
        $output->setTags($object->getTags()->getValues());
        $output->setWorkspace($object->getWorkspace());
        $this->resolveAttributesAndHighlights($object, $output, $highlights);

        $renditions = $this->em
            ->getRepository(AssetRendition::class)
            ->findAssetRenditions($object->getId());

        foreach ([
                     'original',
                     'preview',
                     'thumbnail',
                     'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getRenditionFileOutput($renditions, $object, $type, $userId, $groupIds)) {
                $output->{'set'.ucfirst($type)}($file);
            }
        }

        $output->setCollections($object->getCollections()->map(function (CollectionAsset $collectionAsset): Collection {
            return $collectionAsset->getCollection();
        })
            ->filter(function (Collection $collection): bool {
                return $this->isGranted(CollectionVoter::LIST, $collection);
            })
            ->getValues());

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AssetVoter::EDIT, $object),
            'canDelete' => $this->isGranted(AssetVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(AssetVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    private function resolveAttributesAndHighlights(Asset $asset, AssetOutput $output, ?array $highlights = []): void
    {
        /** @var Attribute[] $attributes */
        $attributes = $this->em->getRepository(Attribute::class)
            ->getAssetAttributes($asset);

        $groupedByDef = [];

        foreach ($attributes as $attribute) {
            $def = $attribute->getDefinition();
            $k = $def->getId();

            if (!isset($groupedByDef[$k])) {
                $groupedByDef[$k] = $attribute;
            }

            if ($def->isMultiple()) {
                $values = $groupedByDef[$k]->getValues() ?? [];
                $values[] = $attribute->getValue();
                $groupedByDef[$k]->setValues($values);
            }
        }

        if (!empty($highlights)) {
            $prefix = 'attributes._.';
            foreach ($groupedByDef as $attribute) {
                $f = $this->fieldNameResolver->getFieldName($attribute->getDefinition());

                if ($h = ($highlights[$prefix.$f] ?? null)) {
                    if ($attribute->getDefinition()->isMultiple()) {
                        $values = $attribute->getValues();
                        $newValues = [];

                        foreach ($values as $v) {
                            $found = false;
                            foreach ($highlights[$prefix.$f] as $hlValue) {
                                if (preg_replace('#\[hl](.*)\[/hl]#', '$1', $hlValue) === $v) {
                                    $found = true;
                                    $newValues[] = $hlValue;
                                    break;
                                }
                            }
                            if (!$found) {
                                $newValues[] = $v;
                            }
                        }

                        $attribute->setHighlights($newValues);
                    } else {
                        $attribute->setHighlight(reset($h));
                    }
                }
            }
        }

        $output->setAttributes(array_values($groupedByDef));
    }

    /**
     * @param AssetRendition[] $assetRenditions
     */
    private function getRenditionFileOutput(
        array $assetRenditions,
        Asset $asset,
        string $type,
        ?string $userId,
        array $groupIds
    ): ?File {
        foreach ($assetRenditions as $rendition) {
            if ($rendition->getDefinition()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getClass(), $userId, $groupIds)) {
                    return $rendition->getFile();
                }
            }
        }

        return null;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }
}
