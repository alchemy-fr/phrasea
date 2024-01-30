<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Api\Model\Output\CollectionOutput;
use App\Elasticsearch\CollectionSearch;
use App\Entity\Core\Collection;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CollectionOutputTransformer implements OutputTransformerInterface
{
    final public const COLLECTION_CACHE_NS = 'coll_visibility';

    use GroupsHelperTrait;
    use UserOutputTransformerTrait;
    use SecurityAwareTrait;

    public function __construct(
        private readonly CollectionSearch $collectionSearch,
        private readonly TagAwareCacheInterface $collectionCache,
        private readonly PermissionManager $permissionManager,
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
        $output = new CollectionOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $output->setTitle($data->getTitle());
        $output->setPrivacy($data->getPrivacy());
        $output->setWorkspace($data->getWorkspace());

        if ($this->hasGroup([
            Collection::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());
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
            $maxDepth = $this->hasGroup(Collection::GROUP_2LEVEL_CHILDREN, $context) ? 2 : 1;
            $depth = $context[$key] ?? 0;
            if ($depth < $maxDepth) {
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

        [$output->shared, $output->public] = $this->collectionCache->get(self::COLLECTION_CACHE_NS.':'.$data->getId(), function (ItemInterface $item) use ($data): array {
            $item->tag(self::COLLECTION_CACHE_NS);
            $shared = false;
            $public = false;
            $pointer = $data;
            while (null !== $pointer) {
                if ($pointer->getPrivacy() >= WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE) {
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
            $output->setCapabilities([
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
