<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\NotifyBundle\Notification\NotifierInterface;
use App\Api\Model\Output\CollectionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\CollectionVoter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CollectionOutputTransformer implements OutputTransformerInterface
{
    use GroupsHelperTrait;
    use UserOutputTransformerTrait;
    use SecurityAwareTrait;
    use UserLocaleTrait;

    final public const string COLLECTION_CACHE_NS = 'coll_visibility';

    public function __construct(
        private readonly CollectionSearch $collectionSearch,
        private readonly TagAwareCacheInterface $collectionCache,
        private readonly PermissionManager $permissionManager,
        private readonly NotifierInterface $notifier,
        #[Autowire(env: 'API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE')]
        private readonly string $ownerPropertyRequiredRole,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return CollectionOutput::class === $outputClass && $data instanceof Collection;
    }

    /**
     * @param Collection $data
     */
    public function transform($data, string $outputClass, array &$context = []): object
    {
        $preferredLocales = $this->getPreferredLocales($data->getWorkspace());

        $output = new CollectionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->deleted = $data->isDeleted();

        $parent = $data->getParent();
        if (null !== $parent && $this->isGranted(AbstractVoter::READ, $parent)) {
            $output->parentId = $parent->getId();
            if ($this->hasGroup([
                Collection::GROUP_ASCENDANTS,
            ], $context)) {
                $output->parent = $parent;
            }
        }

        $storyAsset = $data->getStoryAsset();
        if (null !== $storyAsset) {
            $output->setStoryAsset($storyAsset);
        } else {
            $output->setName($data->getName());
            $output->displayName = $data->getTranslatedField(Collection::TR_FIELD_NAME, $preferredLocales, $data->getName());
        }

        $highlights = $data->getElasticHighlights();
        $output->nameHighlight = $highlights['name'][0] ?? null;

        $output->setPrivacy($data->getPrivacy());
        if ($this->hasGroup([
            Collection::GROUP_LIST,
            Workspace::GROUP_LIST,
        ], $context)) {
            $output->inheritedPrivacy = $data->getInheritedPrivacy();
        }
        $output->setWorkspace($data->getWorkspace());
        $output->setExtraMetadata($data->getExtraMetadata());
        $output->relationExtraMetadata = $data->getRelationExtraMetadata();
        $output->translations = $data->getTranslations();

        if ($this->hasGroup([Collection::GROUP_ABSOLUTE_NAME], $context)) {
            $output->absolutePath = $data->getAbsolutePath();
            $output->absoluteName = $data->getAbsoluteName();
            $output->absoluteDisplayName = $this->getAbsoluteDisplayName($data, $preferredLocales);
        }

        if ($this->hasGroup(Collection::GROUP_CHILDREN, $context)) {
            $maxChildrenLimit = 30;
            if (preg_match('#[&?]childrenLimit=(\d+)#', (string) $context['request_uri'], $regs)) {
                $childrenLimit = $regs[1];
            } else {
                $childrenLimit = $maxChildrenLimit;
            }
            if ($childrenLimit > $maxChildrenLimit) {
                $childrenLimit = $maxChildrenLimit;
            }

            $key = sprintf(AbstractObjectNormalizer::DEPTH_KEY_PATTERN, $output::class, 'children');

            if ($context[AbstractObjectNormalizer::ENABLE_MAX_DEPTH] ?? false) {
                $depth = $context[$key] ?? 0;
                if (null !== $depth && $depth < 1) {
                    if (false !== $data->getHasChildren()) {
                        $collections = $this->collectionSearch->search($context['userId'], $context['groupIds'], [
                            'parent' => $data->getId(),
                            'limit' => $childrenLimit,
                        ]);

                        $output->setChildren($collections);
                    } else {
                        $output->setChildren([]);
                    }
                }
            }
        }

        [$output->shared, $output->public] = $this->collectionCache->get($data->getId(), function (ItemInterface $item) use ($data): array {
            $item->tag(self::COLLECTION_CACHE_NS);
            $shared = false;
            $public = false;
            $pointer = $data;
            while (null !== $pointer) {
                if (in_array($pointer->getPrivacy(), [
                    WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE,
                    WorkspaceItemPrivacyInterface::PUBLIC,
                    WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS,
                ], true)) {
                    $public = true;
                }

                if (
                    !empty($this->permissionManager->getAllowedUsers($pointer, PermissionInterface::VIEW))
                    || !empty($this->permissionManager->getAllowedGroups($pointer, PermissionInterface::VIEW))
                ) {
                    $shared = true;
                }

                if ($shared && $public) {
                    break;
                }

                $pointer = $pointer->getParent();
            }

            return [$shared, $public];
        });

        if ($this->hasGroup([Collection::GROUP_LIST, Collection::GROUP_READ], $context)) {
            $virtualColl = new Collection();
            $virtualColl->setWorkspace($output->getWorkspace());
            $virtualColl->setParent($data);
            $virtualColl->setOwnerId($data->getOwnerId());

            $output->setCapabilities([
                'createAsset' => $this->isGranted(AssetContainerVoterInterface::ASSET_CREATE, $data),
                'createCollection' => $this->isGranted(CollectionVoter::CREATE, $virtualColl),
                'edit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'delete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'editPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        if ($this->hasGroup([
            Collection::GROUP_READ,
        ], $context)) {
            if (empty($this->ownerPropertyRequiredRole) || $this->hasRole($this->ownerPropertyRequiredRole)) {
                $output->owner = $this->transformUser($data->getOwnerId());
            }

            $user = $this->getUser();
            if ($user instanceof JwtUser) {
                $output->topicSubscriptions = $this->notifier->getTopicSubscriptions(
                    $data->getTopicKeys(),
                    $user->getId(),
                );
            }
        }

        return $output;
    }

    public function getAbsoluteDisplayName(Collection $collection, array $preferredLocales): ?string
    {
        $ptr = $collection;
        $path = $ptr->getTranslatedField(Collection::TR_FIELD_NAME, $preferredLocales, $ptr->getName());
        $ptr = $ptr->getParent();
        while ($ptr) {
            $path = $ptr->getTranslatedField(Collection::TR_FIELD_NAME, $preferredLocales, $ptr->getName()).' / '.$path;
            $ptr = $ptr->getParent();
        }

        return $path;
    }
}
