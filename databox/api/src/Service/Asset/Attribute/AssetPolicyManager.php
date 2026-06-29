<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Api\Model\Output\AssetOutput;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetRendition;
use App\Repository\Core\AssetPolicyRepository;

final class AssetPolicyManager
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetPolicyRepository $assetPolicyRepository,
    ) {
    }

    public function applyPolicy(Asset $asset, AssetOutput $assetOutput): void
    {
        $user = $this->getUser();

        $policies = $this->assetPolicyRepository->getAssetPolicies(
            $asset->getWorkspaceId(),
            $user?->getUserIdentifier(),
            $user?->getGroups() ?? []
        );

        foreach ($policies as $policy) {
            if ($this->matchesConditions($asset, $policy->getConditions())) {
                $this->applyPolicyToOutput($assetOutput, $policy->getActions());
            }
        }
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

    private function applyPolicyToOutput(AssetOutput $assetOutput, array $actions): void
    {
        foreach ($actions as $action) {
            switch ($action['action']) {
                case 'hide_rendition':
                    $renditionId = $action['renditionId'];

                    foreach ([
                        'main',
                        'preview',
                        'thumbnail',
                        'animatedThumbnail',
                    ] as $r) {
                        $rendition = $assetOutput->{'get'.ucfirst($r)}();
                        /** @var AssetRendition $rendition */
                        if ($rendition) {

                            if ($rendition->getDefinition()->getId() === $renditionId) {
                                $assetOutput->{'set'.ucfirst($r)}(null);
                            }
                        }
                    }

                    break;
            }
        }
    }
}
