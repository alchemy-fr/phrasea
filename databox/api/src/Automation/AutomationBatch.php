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
     * @param Asset[]       $assets          the assets to process
     * @param string[]      $matchedAssetIds the IDs of all the assets of the batch, including those failed by a previous action
     * @param \Closure|null $keepAlive       extends the lock of the run
     */
    public function __construct(
        public array $assets,
        public array $config,
        public AutomationActionState $state,
        public AutomationRunContext $context,
        public array $matchedAssetIds = [],
        private ?\Closure $keepAlive = null,
    ) {
    }

    /**
     * To call between the assets by an action which may take a while on each of them
     * (e.g. uploads): the run is otherwise only kept alive between the batches.
     */
    public function keepAlive(): void
    {
        if (null !== $this->keepAlive) {
            ($this->keepAlive)();
        }
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
