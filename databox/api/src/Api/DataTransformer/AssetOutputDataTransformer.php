<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use App\Api\Filter\Group\GroupValue;
use App\Api\Model\Output\AssetOutput;
use App\Asset\Attribute\AssetTitleResolver;
use App\Asset\Attribute\AttributesResolver;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\Facet\FacetRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AssetVoter;
use App\Security\Voter\CollectionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AssetOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private EntityManagerInterface $em;
    private RenditionPermissionManager $renditionPermissionManager;
    private AttributesResolver $attributesResolver;
    private AssetTitleResolver $assetTitleResolver;
    private RequestStack $requestStack;
    private FieldNameResolver $fieldNameResolver;
    private ?string $lastGroupKey = null;
    private FacetRegistry $facetRegistry;
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(
        EntityManagerInterface $em,
        RenditionPermissionManager $renditionPermissionManager,
        AttributesResolver $attributesResolver,
        AssetTitleResolver $assetTitleResolver,
        RequestStack $requestStack,
        FieldNameResolver $fieldNameResolver,
        FacetRegistry $facetRegistry,
        AttributeTypeRegistry $attributeTypeRegistry
    ) {
        $this->em = $em;
        $this->renditionPermissionManager = $renditionPermissionManager;
        $this->attributesResolver = $attributesResolver;
        $this->assetTitleResolver = $assetTitleResolver;
        $this->requestStack = $requestStack;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->facetRegistry = $facetRegistry;
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    private function getUserLocales(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            return $request->getLanguages();
        }

        return [];
    }

    /**
     * @param Asset $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $userLocales = $this->getUserLocales();
        $preferredLocales = array_unique(array_filter(array_merge($userLocales, $object->getWorkspace()->getLocaleFallbacks(), [IndexMappingUpdater::NO_LOCALE])));

        $user = $this->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        $output = new AssetOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setEditedAt($object->getEditedAt());
        $output->setAttributesEditedAt($object->getAttributesEditedAt());
        $output->setId($object->getId());

        $output->setSource($object->getSource());

        $highlights = $object->getElasticHighlights();

        if (isset($context['groups']) && in_array('asset:index', $context['groups'], true)) {
            $attributes = $this->attributesResolver->resolveAttributes($object, true);

            if (!empty($highlights)) {
                $this->attributesResolver->assignHighlight($attributes, $highlights);
            }
            $indexByAttrName = [];
            $preferredAttributes = [];
            foreach ($attributes as $_attrs) {
                foreach ($preferredLocales as $l) {
                    if (isset($_attrs[$l]) && null !== $_attrs[$l]->getValue()) {
                        $preferredAttributes[] = $_attrs[$l];
                        $key = $this->fieldNameResolver->getFieldName($_attrs[$l]->getDefinition());
                        $indexByAttrName[$key] = $_attrs[$l]->getValue();
                        continue 2;
                    }
                }
            }
            $output->setAttributes($preferredAttributes);

            $output->setTitle($object->getTitle());
            $titleAttribute = $this->assetTitleResolver->resolveTitle($object, $attributes, $preferredLocales);
            if ($titleAttribute instanceof Attribute) {
                $output->setResolvedTitle($titleAttribute->getValue());
                $output->setTitleHighlight($titleAttribute->getHighlight());
            } else {
                $output->setResolvedTitle($object->getTitle());
                if (isset($highlights['title'])) {
                    $output->setTitleHighlight(reset($highlights['title']));
                }
            }

            $groupBy = $context['groupBy'][0] ?? null;
            if (null !== $groupBy) {
                $groupValue = $this->getGroupValue($groupBy, $object, $indexByAttrName[$groupBy] ?? null);
                $groupKey = $groupValue ? $groupValue->getKey() : null;

                if ($this->lastGroupKey !== $groupKey) {
                    $output->setGroupValue($groupValue);

                    $this->lastGroupKey = $groupKey;
                }
            }
        }

        if (empty($output->getResolvedTitle())) {
            if (null !== $object->getSource()) {
                $output->setResolvedTitle($object->getSource()->getOriginalName());
            }
        }

        $output->setPrivacy($object->getPrivacy());
        $output->setTags($object->getTags()->getValues());
        $output->setWorkspace($object->getWorkspace());

        $renditions = $this->em
            ->getRepository(AssetRendition::class)
            ->findAssetRenditions($object->getId());

        foreach ([
                     'original',
                     'preview',
                     'thumbnail',
                     'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getRenditionUsedAsType($renditions, $object, $type, $userId, $groupIds)) {
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

        if (null !== $object->getPendingUploadToken()) {
            $output->setPendingSourceFile(true);
            $output->setPendingUploadToken($object->getPendingUploadToken());
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AssetVoter::EDIT, $object),
            'canEditAttributes' => $this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $object),
            'canShare' => $this->isGranted(AssetVoter::SHARE, $object),
            'canDelete' => $this->isGranted(AssetVoter::DELETE, $object),
            'canEditPermissions' => $this->isGranted(AssetVoter::EDIT_PERMISSIONS, $object),
        ]);

        return $output;
    }

    /**
     * @param AssetRendition[] $assetRenditions
     */
    private function getRenditionUsedAsType(
        array $assetRenditions,
        Asset $asset,
        string $type,
        ?string $userId,
        array $groupIds
    ): ?AssetRendition {
        foreach ($assetRenditions as $rendition) {
            if ($rendition->getDefinition()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getClass(), $userId, $groupIds)) {
                    return $rendition;
                }
            }
        }

        return null;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AssetOutput::class === $to && $data instanceof Asset;
    }

    private function getGroupValue($groupBy, Asset $object, $indexValue): ?GroupValue
    {
        $facet = $this->facetRegistry->getFacet($groupBy);

        if (null !== $facet) {
            $value = $facet->getValueFromAsset($object);

            return $facet->resolveGroupValue($value);
        } else {
            ['type' => $type] = $this->fieldNameResolver->getFieldFromName($groupBy);
            $key = $value = $indexValue ?? null;
            if (null === $key) {
                return null;
            }
            if (is_array($key)) {
                $key = implode(',', $key);
            }
            $value = $type->getGroupValueLabel($type->denormalizeValue($value));

            return new GroupValue($type::getName(), $key, [$value]);
        }
    }
}
