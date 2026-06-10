<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Api\Model\Output\AssetOutput;
use App\Api\Model\Output\ResolveEntitiesOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Elasticsearch\BuiltInField\BuiltInAttributeRegistry;
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
use App\Security\ClientUrlHelper;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use App\Service\Asset\Attribute\AssetNameResolver;
use App\Service\Asset\Attribute\AttributesResolver;
use App\Service\Discussion\DiscussionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AssetOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributesResolver $attributesResolver,
        private readonly AssetNameResolver $assetNameResolver,
        private readonly FieldNameResolver $fieldNameResolver,
        private readonly BuiltInAttributeRegistry $builtInFieldRegistry,
        private readonly AttributeTypeRegistry $attributeTypeRegistry,
        private readonly DiscussionManager $discussionManager,
        private readonly NotifierInterface $notifier,
        #[Autowire(env: 'API_ASSET_OWNER_PROPERTY_REQUIRED_ROLE')]
        private readonly string $ownerPropertyRequiredRole,
        private readonly ClientUrlHelper $clientUrlHelper,
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

        $user = $this->getUser();

        $output->setExtraMetadata($data->getExtraMetadata());
        $output->deleted = $data->isDeleted();
        $output->trackingId = $data->getTrackingId();
        $output->externalId = $data->getExternalId();
        $output->resolvedTrackingId = $data->getResolvedTrackingId();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->editedAt = $data->getEditedAt();
        $output->attributesEditedAt = $data->getAttributesEditedAt();

        $output->setSource($data->getSource());

        $highlights = $data->getElasticHighlights();

        if ($this->hasGroup([
            Asset::GROUP_LIST,
            Asset::GROUP_STORY,
            Share::GROUP_READ,
            Share::GROUP_PUBLIC_READ,
            ResolveEntitiesOutput::GROUP_READ,
        ], $context)) {
            $attributesIndex = $data->attributesIndex ?? $this->attributesResolver->resolveAssetAttributes($data, true);
            $attributes = $attributesIndex->getFlattenAttributes();

            if (!empty($highlights)) {
                $this->attributesResolver->assignHighlight($attributes, $highlights);
            }
            $output->setAttributes($attributes);

            $output->setName($data->getName());
            $nameAttribute = $this->assetNameResolver->resolveName($data, $attributesIndex);
            if ($nameAttribute instanceof Attribute) {
                $output->setName($nameAttribute->getValue());
                $output->setNameHighlight($nameAttribute->getHighlight());
            } else {
                $output->setName($nameAttribute);
                if (isset($highlights['name'])) {
                    $output->setNameHighlight(reset($highlights['name']));
                }
            }

            $output->setGroupValue($data->groupValue);
            $output->setPrivacy($data->getPrivacy());
            $output->setTags($data->getTags()->getValues());
            $output->setWorkspace($data->getWorkspace());

            $renditions = $this->em
                ->getRepository(AssetRendition::class)
                ->findAssetRenditions($data->getId(), [
                    AssetRenditionRepository::OPT_USED_AS => true,
                ]);

            foreach (RenditionDefinition::BUILT_IN_RENDITIONS as $type) {
                if (null !== $file = $this->getRenditionUsedAsType($renditions, $type)) {
                    $output->{'set'.ucfirst($type)}($file);
                }
            }

            $output->webUrl = $this->clientUrlHelper->generateAssetUrl($data);

            $referenceCollection = $data->getReferenceCollection();
            if (null !== $referenceCollection && $this->isGranted(AbstractVoter::READ, $referenceCollection)) {
                $output->referenceCollection = $referenceCollection;
            }

            $output->setCollections($data->getCollections()->map(function (CollectionAsset $collectionAsset,
            ): Collection {
                $collection = $collectionAsset->getCollection();
                $collection->setRelationExtraMetadata($collectionAsset->getExtraMetadata());

                return $collection;
            })
                ->filter(fn (Collection $collection): bool => !$collection->isDeleted() && $this->isGranted(AbstractVoter::READ, $collection))
                ->getValues());

            $output->storyCollection = $data->getStoryCollection();
        }

        if ($this->hasGroup([Asset::GROUP_LIST], $context)) {
            $capabilities = [
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'editAttributes' => $this->isGranted(AssetVoter::EDIT_ATTRIBUTES, $data),
                'share' => $this->isGranted(AssetVoter::SHARE, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
            ];

            if ($this->hasGroup([Asset::GROUP_READ], $context)) {
                $capabilities['editPermissions'] = $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data);
            }

            $output->setCapabilities($capabilities);

            if (empty($this->ownerPropertyRequiredRole) || $this->hasRole($this->ownerPropertyRequiredRole)) {
                $output->owner = $this->transformUser($data->getOwnerId());
            }

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
        string $type,
    ): ?AssetRendition {
        foreach ($assetRenditions as $rendition) {
            if ($rendition->getDefinition()->{'isUseAs'.ucfirst($type)}()) {
                // Return the first viewable sub def for user
                if ($this->isGranted(AbstractVoter::READ, $rendition)) {
                    return $rendition;
                }
            }
        }

        return null;
    }
}
