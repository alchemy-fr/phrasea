<?php

declare(strict_types=1);

namespace App\Automation;

use App\Entity\Core\Asset;

/**
 * A batch of assets handed to an action, with its decrypted configuration.
 */
final readonly class AutomationBatch
{
    /**
     * @param Asset[] $assets
     */
    public function __construct(
        public array $assets,
        public array $config,
        public AutomationActionState $state,
        public AutomationRunContext $context,
    ) {
    }

    /**
     * @return array<string, Asset[]> the assets indexed by workspace ID
     */
    public function groupByWorkspace(): array
    {
        $groups = [];
        foreach ($this->assets as $asset) {
            $groups[$asset->getWorkspaceId()][] = $asset;
        }

        return $groups;
    }
}
