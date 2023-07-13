<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\RemoteAuthBundle\Model\RemoteUser;
use ApiPlatform\Metadata\Operation;
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

class AssetOutputProcessor extends AbstractSecurityProcessor
{
    private ?string $lastGroupKey = null;

    public function __construct(private readonly EntityManagerInterface $em, private readonly RenditionPermissionManager $renditionPermissionManager, private readonly AttributesResolver $attributesResolver, private readonly AssetTitleResolver $assetTitleResolver, private readonly RequestStack $requestStack, private readonly FieldNameResolver $fieldNameResolver, private readonly FacetRegistry $facetRegistry, private readonly AttributeTypeRegistry $attributeTypeRegistry)
    {
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
     * @param Asset $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $userLocales = $this->getUserLocales();
        $preferredLocales = array_unique(array_filter(array_merge($userLocales, $data->getWorkspace()->getLocaleFallbacks(), [IndexMappingUpdater::NO_LOCALE])));

        $user = $this->getUser();
        $userId = $user instanceof RemoteUser ? $user->getId() : null;
        $groupIds = $user instanceof RemoteUser ? $user->getGroupIds() : [];

        $output = new AssetOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setEditedAt($data->getEditedAt());
        $output->setAttributesEditedAt($data->getAttributesEditedAt());
        $output->setId($data->getId());

        $output->setSource($data->getSource());

        $highlights = $data->getElasticHighlights();

        if (isset($context['groups']) && in_array('asset:index', $context['groups'], true)) {
            $attributes = $this->attributesResolver->resolveAssetAttributes($data, true);

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

            $output->setTitle($data->getTitle());
            $titleAttribute = $this->assetTitleResolver->resolveTitle($data, $attributes, $preferredLocales);
            if ($titleAttribute instanceof Attribute) {
                $output->setResolvedTitle($titleAttribute->getValue());
                $output->setTitleHighlight($titleAttribute->getHighlight());
            } else {
                $output->setResolvedTitle($data->getTitle());
                if (isset($highlights['title'])) {
                    $output->setTitleHighlight(reset($highlights['title']));
                }
            }

            $groupBy = $context['groupBy'][0] ?? null;
            if (null !== $groupBy) {
                $groupValue = $this->getGroupValue($groupBy, $data, $indexByAttrName[$groupBy] ?? null);
                $groupKey = $groupValue ? $groupValue->getKey() : null;

                if ($this->lastGroupKey !== $groupKey) {
                    $output->setGroupValue($groupValue);

                    $this->lastGroupKey = $groupKey;
                }
            }
        }

        if (empty($output->getResolvedTitle())) {
            if (null !== $data->getSource()) {
                $output->setResolvedTitle($data->getSource()->getOriginalName());
            }
        }

        $output->setPrivacy($data->getPrivacy());
        $output->setTags($data->getTags()->getValues());
        $output->setWorkspace($data->getWorkspace());

        $renditions = $this->em
            ->getRepository(AssetRendition::class)
            ->findAssetRenditions($data->getId());

        foreach ([
                     'original',
                     'preview',
                     'thumbnail',
                     'thumbnailActive',
                 ] as $type) {
            if (null !== $file = $this->getRenditionUsedAsType($renditions, $data, $type, $userId, $groupIds)) {
                $output->{'set'.ucfirst($type)}($file);
            }
        }

        $output->setCollections($data->getCollections()->map(fn (CollectionAsset $collectionAsset): Collection => $collectionAsset->getCollection())
            ->filter(fn (Collection $collection): bool => $this->isGranted(CollectionVoter::LIST, $collection))
            ->getValues());

        if (null !== $data->getPendingUploadToken()) {
            $output->setPendingSourceFile(true);
            $output->setPendingUploadToken($data->getPendingUploadToken());
        }

        $output->setCapabilities([
            'canEdit' => $this->isGranted(AssetVoter::EDIT, $data),
            'canEditAttributes' => $this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $data),
            'canShare' => $this->isGranted(AssetVoter::SHARE, $data),
            'canDelete' => $this->isGranted(AssetVoter::DELETE, $data),
            'canEditPermissions' => $this->isGranted(AssetVoter::EDIT_PERMISSIONS, $data),
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

    private function getGroupValue($groupBy, Asset $object, $indexValue): GroupValue
    {
        $facet = $this->facetRegistry->getFacet($groupBy);

        if (null !== $facet) {
            $value = $facet->getValueFromAsset($object);

            return $facet->resolveGroupValue($groupBy, $value);
        } else {
            ['type' => $type] = $this->fieldNameResolver->getFieldFromName($groupBy);
            $key = $value = $indexValue ?? null;
            if (is_array($key)) {
                $key = implode(',', $key);
            }
            $value = $type->getGroupValueLabel($type->denormalizeValue($value));

            return new GroupValue($groupBy, $type::getName(), $key, null !== $value ? [$value] : []);
        }
    }
}