<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Api\Filter\Group\GroupValue;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\BuiltInField\BuiltInFieldRegistry;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Basket\BasketAsset;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetAttachment;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\Attribute;
use App\Entity\Core\Collection;
use App\Entity\Core\CollectionAsset;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\Share;
use App\Repository\Core\AssetRenditionRepository;
use App\Security\RenditionPermissionManager;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use App\Service\Asset\Attribute\AssetTitleResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Discussion\DiscussionManager;
use Doctrine\ORM\EntityManagerInterface;

class AssetOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    private ?string $lastGroupKey = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RenditionPermissionManager $renditionPermissionManager,
        private readonly AttributesResolver $attributesResolver,
        private readonly AssetTitleResolver $assetTitleResolver,
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly BuiltInFieldRegistry $builtInFieldRegistry,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly DiscussionManager $discussionManager,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return AssetOutput::class === $outputClass && $data instanceof Asset;
    }

    /**
     * @param Asset $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new AssetOutput();
        $output->setId($data->getId());

        if ($this->hasGroup(BasketAsset::GROUP_LIST, $context)) {
            if (!$this->isGranted(AbstractVoter::READ, $data)) {
                return $output;
            }
        }

        $preferredLocales = $this->getPreferredLocales($data->getWorkspace());

        $user = $this->getUser();
        $userId = $user instanceof JwtUser ? $user->getId() : null;
        $groupIds = $user instanceof JwtUser ? $user->getGroups() : [];

        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setEditedAt($data->getEditedAt());
        $output->setAttributesEditedAt($data->getAttributesEditedAt());
        $output->setExtraMetadata($data->getExtraMetadata());
        $output->deleted = $data->isDeleted();
        $output->trackingId = $data->getTrackingId();
        $output->externalId = $data->getExternalId();
        $output->resolvedTrackingId = $data->getResolvedTrackingId();

        $output->setSource($data->getSource());

        $highlights = $data->getElasticHighlights();

        if ($this->hasGroup([
            Asset::GROUP_LIST,
            Asset::GROUP_STORY,
            Share::GROUP_READ,
            Share::GROUP_PUBLIC_READ,
            ResolveEntitiesOutput::GROUP_READ,
        ], $context)) {
            $attributesIndex = $this->attributesResolver->resolveAssetAttributes($data, true);
            $attributes = $attributesIndex->getFlattenAttributes();

            if (!empty($highlights)) {
                $this->attributesResolver->assignHighlight($attributes, $highlights);
            }
            $output->setAttributes($attributes);

            $output->setTitle($data->getTitle());
            $titleAttribute = $this->assetTitleResolver->resolveTitle($data, $attributesIndex, $preferredLocales);
            if ($titleAttribute instanceof Attribute) {
                $output->setResolvedTitle($titleAttribute->getValue());
                $output->setTitleHighlight($titleAttribute->getHighlight());
            } else {
                $output->setResolvedTitle($titleAttribute ?? $data->getTitle());
                if (isset($highlights['title'])) {
                    $output->setTitleHighlight(reset($highlights['title']));
                }
            }

            $groupBy = $context['groupBy'][0] ?? null;
            if (null !== $groupBy) {
                $indexValue = null;
                foreach ($attributesIndex->getDefinitions() as $definitionIndex) {
                    if ($groupBy === $this->fieldNameResolver->getFieldNameFromDefinition($definitionIndex->getDefinition())) {
                        foreach ($preferredLocales as $l) {
                            if ($definitionIndex->getDefinition()->isMultiple()) {
                                continue;
                            }
                            if (null !== $attr = $definitionIndex->getAttribute($l)) {
                                $indexValue = $attr->getValue();
                                break 2;
                            }
                        }

                        break;
                    }
                }

                $groupValue = $this->getGroupValue($groupBy, $data, $indexValue);
                $groupKey = $groupValue->getKey();

                if ($this->lastGroupKey !== $groupKey) {
                    $output->setGroupValue($groupValue);

                    $this->lastGroupKey = $groupKey;
                }
            }

            $output->setPrivacy($data->getPrivacy());
            $output->setTags($data->getTags()->getValues());
            $output->setWorkspace($data->getWorkspace());

            $renditions = $this->em
                ->getRepository(AssetRendition::class)
                ->findAssetRenditions($data->getId(), [
                    AssetRenditionRepository::OPT_USED_AS => true,
                ]);

            foreach (RenditionDefinition::BUILT_IN_RENDITIONS as $type) {
                if (null !== $file = $this->getRenditionUsedAsType($renditions, $data, $type, $userId, $groupIds)) {
                    $output->{'set'.ucfirst($type)}($file);
                }
            }

            $output->referenceCollection = $data->getReferenceCollection();

            $output->setCollections($data->getCollections()->map(function (CollectionAsset $collectionAsset,
            ): Collection {
                $collection = $collectionAsset->getCollection();
                $collection->setRelationExtraMetadata($collectionAsset->getExtraMetadata());

                return $collection;
            })
                ->filter(fn (Collection $collection): bool => $this->isGranted(AbstractVoter::LIST, $collection))
                ->getValues());

            $output->storyCollection = $data->getStoryCollection();
        }

        if ($this->hasGroup([Asset::GROUP_LIST], $context)) {
            $capabilities = [
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canEditAttributes' => $this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $data),
                'canShare' => $this->isGranted(AssetVoter::SHARE, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
            ];

            if ($this->hasGroup([Asset::GROUP_READ], $context)) {
                $capabilities['canEditPermissions'] = $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data);
            }

            $output->setCapabilities($capabilities);

            $output->owner = $this->transformUser($data->getOwnerId());
            $output->threadKey = $this->discussionManager->getObjectKey($data);
        }

        if ($this->hasGroup([Asset::GROUP_READ], $context)) {
            if ($user instanceof JwtUser) {
                $output->topicSubscriptions = $this->notifier->getTopicSubscriptions(
                    $data->getTopicKeys(),
                    $user->getId(),
                );
            }

            $output->thread = $this->discussionManager->getThreadOfObject($data);
            $output->attachments = array_filter($data->getAttachments()->getValues(), fn (AssetAttachment $attachment): bool => !$attachment->getAsset()->isDeleted() && $this->isGranted(AbstractVoter::READ, $attachment->getAsset()));
        }

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
        array $groupIds,
    ): ?AssetRendition {
        foreach ($assetRenditions as $rendition) {
            if ($rendition->getDefinition()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->renditionPermissionManager->isGranted($asset, $rendition->getDefinition()->getPolicy(), $userId, $groupIds)) {
                    return $rendition;
                }
            }
        }

        return null;
    }

    private function getGroupValue($groupBy, Asset $object, $indexValue): GroupValue
    {
        $builtInField = $this->builtInFieldRegistry->getBuiltInField($groupBy);

        if (null !== $builtInField) {
            $value = $builtInField->getValueFromAsset($object);

            return $builtInField->resolveGroupValue($groupBy, $value);
        }

        ['type' => $type] = $this->fieldNameResolver->getFieldFromName($groupBy);
        $key = $value = $indexValue ?? null;
        if (is_array($key)) {
            $key = implode(',', $key);
        }
        $value = $type->getGroupValueLabel($type->denormalizeValue($value));

        return new GroupValue($groupBy, $type::getName(), $key, null !== $value ? [$value] : []);

    }
}
