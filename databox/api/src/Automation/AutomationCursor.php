<?php

declare(strict_types=1);

namespace App\Automation;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Repository\Automation\AutomationSeenAssetRepository;

/**
 * Resets the set of assets an automation has already handled.
 */
final readonly class AutomationCursor
{
    public function __construct(
        private AutomationSeenAssetRepository $seenAssetRepository,
        private UserContextSwitcher $userContextSwitcher,
        private AutomationAssetIterator $assetIterator,
    ) {
    }

    /**
     * Every result becomes new again.
     */
    public function empty(SavedSearchAutomation $automation): void
    {
        $this->seenAssetRepository->reset($automation->getId());
    }

    /**
     * Marks every current result (as seen by the owner) as handled, without running any action.
     *
     * @return int the number of results marked
     *
     * @throws AutomationOwnerUnavailableException
     */
    public function markCurrentResultsAsSeen(SavedSearchAutomation $automation): int
    {
        $ownerId = $automation->getOwnerId() ?? throw new AutomationOwnerUnavailableException('The saved search has no owner');

        return $this->userContextSwitcher->runAs($ownerId, function (JwtUser $user) use ($automation): int {
            $count = 0;
            foreach ($this->assetIterator->iterate($automation, $user, true) as $batch) {
                $this->seenAssetRepository->markSeen(
                    $automation->getId(),
                    array_map(fn (Asset $asset): string => $asset->getId(), $batch->assets),
                );
                $count += count($batch->assets);
            }

            return $count;
        });
    }
}
