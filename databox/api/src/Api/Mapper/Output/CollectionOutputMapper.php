<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\NotifierBundle\Manager\SubscriptionManager;
use App\Api\Model\Output\CollectionOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Entity\Core\Workspace;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetContainerVoterInterface;
use App\Security\Voter\CollectionVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsTaggedItem(index: CollectionOutput::class)]
class CollectionOutputMapper implements OutputMapperInterface
{
    use GroupsHelperTrait;
    use UserOutputTrait;
    use SecurityAwareTrait;
    use UserLocaleTrait;

    final public const string COLLECTION_CACHE_NS = 'coll_visibility';

    /**
     * Keeps a UUID such as "2e19…" from being taken for a number by the cache adapters.
     */
    private const string CACHE_KEY_PREFIX = 'c.';

    /**
     * Same as Collection::getAbsoluteName(): non-breaking spaces around the slash.
     */
    private const string PATH_SEPARATOR = "\u{a0}/\u{a0}";

    private readonly CacheInterface $visibilityRequestCache;

    /**
     * READ decisions of the current user: never to be stored in the shared collection cache.
     */
    private readonly CacheInterface $readRequestCache;

    public function __construct(
        private readonly CollectionSearch $collectionSearch,
        private readonly TagAwareCacheInterface $collectionCache,
        private readonly PermissionManager $permissionManager,
        private readonly SubscriptionManager $subscriptionManager,
        #[Autowire(env: 'API_COLLECTION_OWNER_PROPERTY_REQUIRED_ROLE')]
        private readonly string $ownerPropertyRequiredRole,
        TemporaryCacheFactory $cacheFactory,
    ) {
        $this->visibilityRequestCache = $cacheFactory->createCache();
        $this->readRequestCache = $cacheFactory->createCache();
    }

    public function supports(object $data): bool
    {
        return $data instanceof Collection;
    }

    /**
     * @param Collection $data
     */
    public function map(object $data, array $context = []): object
    {
        $preferredLocales = $this->getPreferredLocales($data->getWorkspace());

        $output = new CollectionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->deleted = $data->isDeleted();

        $parent = $data->getParent();
        if (null !== $parent && $this->isReadable($parent)) {
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

        $output->nameHighlight = self::getNameHighlight($data->getElasticHighlights() ?? [], $output->displayName ?? $output->getName());

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
            $branch = $this->getReadableBranch($data);
            $output->absolutePath = '/'.implode('/', array_map(static fn (Collection $c): string => $c->getId(), $branch));
            $output->absoluteName = self::joinNames($branch, static fn (Collection $c): ?string => $c->getName());
            $output->absoluteDisplayName = self::joinNames($branch, static fn (Collection $c): ?string => $c->getTranslatedField(Collection::TR_FIELD_NAME, $preferredLocales, $c->getName()));
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

        // The same collection is embedded many times in a page of assets:
        // hit the shared cache once per request.
        $cacheKey = self::CACHE_KEY_PREFIX.$data->getId();
        [$output->shared, $output->public] = $this->visibilityRequestCache->get($cacheKey, fn (): array => $this->collectionCache->get($cacheKey, function (ItemInterface $item) use ($data): array {
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
        }));

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
                $output->topicSubscriptions = $this->subscriptionManager->getSubscribedEvents(
                    $user->getId(),
                    $data->getObjectType(),
                    $data->getId(),
                );
            }
        }

        return $output;
    }

    /**
     * The collection and its ancestors, from the root down, cut at the first ancestor the user
     * cannot read: the path must not disclose the name or the ID of an unreadable collection.
     *
     * @return Collection[]
     */
    private function getReadableBranch(Collection $collection): array
    {
        $branch = [$collection];
        $ptr = $collection->getParent();
        while (null !== $ptr && $this->isReadable($ptr)) {
            $branch[] = $ptr;
            $ptr = $ptr->getParent();
        }

        return array_reverse($branch);
    }

    /**
     * @param Collection[]                  $branch
     * @param callable(Collection): ?string $getName
     */
    private static function joinNames(array $branch, callable $getName): ?string
    {
        if (1 === count($branch)) {
            return $getName($branch[0]);
        }

        return implode(self::PATH_SEPARATOR, array_map(static fn (Collection $c): string => (string) $getName($c), $branch));
    }

    private function isReadable(Collection $collection): bool
    {
        // The same ancestors come back for every collection embedded in a page of assets
        return $this->readRequestCache->get(
            self::CACHE_KEY_PREFIX.$collection->getId(),
            fn (): bool => $this->isGranted(AbstractVoter::READ, $collection),
        );
    }

    /**
     * The highlight of the displayed name: the name may match through one of its translations.
     *
     * The partial highlight (only the typed part of the words) comes first; the whole words one
     * remains for the words longer than the indexed edge n-grams.
     */
    private static function getNameHighlight(array $highlights, ?string $displayedName): ?string
    {
        if (null !== $displayedName) {
            foreach ([
                ...$highlights['name.suggest'] ?? [],
                ...$highlights['translatedNames.suggest'] ?? [],
                ...$highlights['name'] ?? [],
                ...$highlights['translatedNames'] ?? [],
            ] as $fragment) {
                if (str_replace(['[hl]', '[/hl]'], '', $fragment) === $displayedName) {
                    return $fragment;
                }
            }
        }

        return $highlights['name.suggest'][0] ?? $highlights['name'][0] ?? null;
    }
}
