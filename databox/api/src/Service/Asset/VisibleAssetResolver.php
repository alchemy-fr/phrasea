<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Elasticsearch\AssetSearch;
use App\Entity\Core\Asset;
use App\Security\AttributeFilterManager;
use App\Security\Voter\AbstractVoter;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Tells which of already loaded assets the current user may see in a listing.
 *
 * Visible = READ granted by the AssetVoter and, when the user has an attribute filter
 * rule on the workspace of the asset, the asset matched by the search base filters in
 * Elasticsearch (ACL + rules), as in the search. Without any rule on the workspace,
 * the voter alone decides and Elasticsearch is not queried.
 *
 * Attribute filter rules are a listing filter, not an access right: accesses by ID
 * stay decided by the voter alone.
 */
class VisibleAssetResolver implements ResetInterface
{
    use SecurityAwareTrait;

    /**
     * @var array<string, array<string, bool>> decisions by user then asset ID
     */
    private array $decisions = [];

    /**
     * @var array<string, array<string, string[]>> rule conditions by user then workspace ID
     */
    private array $rules = [];

    public function __construct(
        private readonly AttributeFilterManager $attributeFilterManager,
        private readonly AssetSearch $assetSearch,
    ) {
    }

    /**
     * @param Asset[] $assets
     *
     * @return Asset[] the visible assets, in the given order
     */
    public function filterVisible(array $assets): array
    {
        if (empty($assets)) {
            return [];
        }

        $user = $this->getUser();
        $userId = $user?->getId();
        $groupIds = $user?->getGroups() ?? [];
        $userKey = $this->getUserCacheId();
        $this->decisions[$userKey] ??= [];
        $decisions = &$this->decisions[$userKey];

        $toSearch = [];
        foreach ($assets as $asset) {
            $id = $asset->getId();
            if (isset($decisions[$id]) || isset($toSearch[$id])) {
                continue;
            }

            if (!$this->isGranted(AbstractVoter::READ, $asset)) {
                $decisions[$id] = false;
            } elseif (isset($this->getRules($userKey, $userId, $groupIds)[$asset->getWorkspaceId()])) {
                $toSearch[$id] = true;
            } else {
                $decisions[$id] = true;
            }
        }

        if (!empty($toSearch)) {
            $visibleIds = array_flip($this->assetSearch->getVisibleIds($userId, $groupIds, array_keys($toSearch)));
            foreach (array_keys($toSearch) as $id) {
                $decisions[$id] = isset($visibleIds[$id]);
            }
        }

        return array_values(array_filter($assets, static fn (Asset $asset): bool => $decisions[$asset->getId()]));
    }

    public function isVisible(Asset $asset): bool
    {
        return [] !== $this->filterVisible([$asset]);
    }

    /**
     * @return array<string, string[]>
     */
    private function getRules(string $userKey, ?string $userId, array $groupIds): array
    {
        return $this->rules[$userKey] ??= $this->attributeFilterManager->getUserRules($userId, $groupIds);
    }

    public function reset(): void
    {
        $this->decisions = [];
        $this->rules = [];
    }
}
