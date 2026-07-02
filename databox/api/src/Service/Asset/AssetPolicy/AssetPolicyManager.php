<?php

declare(strict_types=1);

namespace App\Service\Asset\AssetPolicy;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetPolicyRepository;

final class AssetPolicyManager
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetPolicyRepository $assetPolicyRepository,
    ) {
    }

    public function getPolicyApplicationFilter(Asset $asset): AssetPolicyResultFilter
    {
        $user = $this->getUser();

        $policies = $this->assetPolicyRepository->getAssetPolicies(
            $asset->getWorkspaceId(),
            $user?->getUserIdentifier(),
            $user?->getGroups() ?? []
        );

        $filter = new AssetPolicyResultFilter();

        foreach ($policies as $policy) {
            if ($this->matchesConditions($asset, $policy->getConditions())) {
                $this->applyPolicyToOutput($policy->getActions(), $filter);
            }
        }

        return $filter;
    }

    private function matchesConditions(Asset $asset, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            switch ($condition['field']) {
                case 'collection':
                    switch ($condition['operator']) {
                        case '=':
                            if (!str_contains($asset->getReferenceCollection()?->getAbsolutePath() ?? '', $condition['value'])) {
                                return false;
                            }
                            break;
                        default:
                            return false;
                    }
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    private function applyPolicyToOutput(array $actions, AssetPolicyResultFilter $filter): void
    {
        foreach ($actions as $action) {
            switch ($action['action']) {
                case 'hide_rendition':
                    $filter->addFilteredRendition($action['definitionId']);
                    break;
                case 'hide_attribute':
                    $filter->addFilteredAttribute($action['definitionId']);
                    break;
            }
        }
    }
}
