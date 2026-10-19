<?php

declare(strict_types=1);

namespace App\Automation;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Elasticsearch\AssetSearch;
use App\Entity\Automation\SavedSearchAutomation;
use App\Entity\Core\Asset;
use App\Repository\Automation\AutomationSeenAssetRepository;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Iterates over the results of the saved search of an automation, as the given user
 * (who must be the current user: READ is checked on each asset).
 */
final readonly class AutomationAssetIterator
{
    private const int SEARCH_BATCH_SIZE = 500;
    private const int LOAD_BATCH_SIZE = 200;

    public function __construct(
        private AssetSearch $assetSearch,
        private AssetRepository $assetRepository,
        private AutomationSeenAssetRepository $seenAssetRepository,
        private Security $security,
    ) {
    }

    /**
     * @return \Generator<int, AutomationIteratorBatch>
     */
    public function iterate(SavedSearchAutomation $automation, JwtUser $user, bool $onlyNew): \Generator
    {
        $automationId = $automation->getId();
        $ids = $this->assetSearch->iterateIds($user->getId(), $user->getGroups(), [
            'savedSearch' => $automation->getSavedSearch()->getId(),
        ], self::SEARCH_BATCH_SIZE);

        foreach ($ids as $searchBatch) {
            foreach (array_chunk($searchBatch, self::LOAD_BATCH_SIZE) as $chunk) {
                $assets = array_values(array_filter(
                    $this->assetRepository->findByIds($chunk),
                    fn (Asset $asset): bool => $this->security->isGranted(AbstractVoter::READ, $asset),
                ));
                if (empty($assets)) {
                    continue;
                }

                $unseen = array_flip($this->seenAssetRepository->filterUnseen(
                    $automationId,
                    array_map(fn (Asset $asset): string => $asset->getId(), $assets),
                ));
                $newAssets = array_values(array_filter($assets, fn (Asset $asset): bool => isset($unseen[$asset->getId()])));

                yield new AutomationIteratorBatch(
                    matchedCount: count($assets),
                    newCount: count($newAssets),
                    assets: $onlyNew ? $newAssets : $assets,
                );
            }
        }
    }
}
