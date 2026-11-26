<?php

declare(strict_types=1);

namespace App\Service\Asset;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use App\Doctrine\Delete\AssetDelete;
use App\Entity\Core\Asset;
use App\Entity\Core\AssetStatusEnum;
use App\Model\QuarantineBatchActionEnum;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\AssetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The ways out of quarantine, and the rules telling whether a quarantined
 * asset may take one of them. Shared by the single-asset endpoints and the
 * batch ones, which check these rules before and again at execution time.
 *
 * @phpstan-type MergeTarget array{asset: Asset, analyzers: string[], validTarget: bool}
 */
class QuarantineResolver
{
    use SecurityAwareTrait;

    final public const string REASON_NOT_FOUND = 'not_found';
    final public const string REASON_NOT_QUARANTINED = 'not_quarantined';
    final public const string REASON_FORBIDDEN = 'forbidden';
    final public const string REASON_NO_DUPLICATE = 'no_duplicate';
    final public const string REASON_NO_VALID_TARGET = 'no_valid_target';
    final public const string REASON_TARGET_REQUIRED = 'target_required';
    final public const string REASON_INVALID_TARGET = 'invalid_target';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AssetManager $assetManager,
        private readonly AssetDelete $assetDelete,
        private readonly DuplicateAssetResolver $duplicateAssetResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Accepts the asset despite its failed file analysis: its source file
     * analysis is marked as bypassed (the analyzer results are kept for
     * reference).
     */
    public function bypass(Asset $asset): void
    {
        $source = $asset->getSource();
        if (null !== $source) {
            $source->bypassAnalysis();
            $this->em->persist($source);
        }

        $asset->setStatus(AssetStatusEnum::Accepted);
        $this->em->persist($asset);
        $this->em->flush();
    }

    /**
     * Attaches the quarantined asset's source file to the target as its new
     * current source (the previous one becomes a file version), then removes
     * the quarantined asset.
     */
    public function addAsVersion(Asset $quarantined, Asset $target): void
    {
        $file = $quarantined->getSource() ?? throw new \InvalidArgumentException('Quarantined asset has no source file.');

        $quarantinedId = $quarantined->getId();
        // The target never loses its source without the quarantined asset being removed, nor the opposite
        $this->em->wrapInTransaction(function () use ($quarantinedId, $target, $file): void {
            $this->assetManager->assignNewAssetSourceFile($target, $file);
            $this->em->flush();

            $this->assetDelete->deleteFromDatabase([$quarantinedId]);
        });

        try {
            $this->assetDelete->deleteFromIndex([$quarantinedId]);
        } catch (\Throwable $e) {
            // The asset is gone from the database: its document only lingers in the index
            $this->logger->error(sprintf('Unable to remove the merged asset %s from the index: %s', $quarantinedId, $e->getMessage()), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * The duplicates of the asset the current user can read. A valid target
     * is one the incoming file can be added to as a new version.
     *
     * @return list<MergeTarget>
     */
    public function resolveMergeTargets(Asset $asset): array
    {
        $source = $asset->getSource();
        if (null === $source) {
            return [];
        }

        $targets = [];
        foreach ($this->duplicateAssetResolver->resolveDuplicates($source, excludeAssetId: $asset->getId()) as $duplicate) {
            $duplicate['validTarget'] = self::isAccepted($duplicate['asset'])
                && $duplicate['asset']->getWorkspaceId() === $asset->getWorkspaceId()
                && $this->isGranted(AbstractVoter::EDIT, $duplicate['asset']);
            $targets[] = $duplicate;
        }

        return $targets;
    }

    /**
     * Whatever the action: the asset must still exist, be readable and be in quarantine.
     *
     * @return string|null why the asset cannot take any action, null when it can be resolved
     */
    public function checkQuarantined(?Asset $asset): ?string
    {
        if (null === $asset || $asset->isDeleted()) {
            return self::REASON_NOT_FOUND;
        }
        if (!$this->isGranted(AbstractVoter::READ, $asset)) {
            return self::REASON_FORBIDDEN;
        }
        if (AssetStatusEnum::Quarantined !== $asset->getStatus()) {
            return self::REASON_NOT_QUARANTINED;
        }

        return null;
    }

    /**
     * @param bool                   $addAsVersion merge only: whether the incoming file becomes a version of the
     *                                             target, or is discarded
     * @param list<MergeTarget>|null $mergeTargets merge only: resolveMergeTargets() of the asset, when already known
     *
     * @return string|null why the asset cannot take this action, null when it can
     */
    public function checkEligibility(
        ?Asset $asset,
        QuarantineBatchActionEnum $action,
        bool $addAsVersion = true,
        ?array $mergeTargets = null,
    ): ?string {
        $reason = $this->checkQuarantined($asset);
        if (null !== $reason || null === $asset) {
            return $reason ?? self::REASON_NOT_FOUND;
        }

        return match ($action) {
            QuarantineBatchActionEnum::Bypass => $this->isGranted(AssetVoter::QUARANTINE_BYPASS, $asset) ? null : self::REASON_FORBIDDEN,
            QuarantineBatchActionEnum::Trash,
            QuarantineBatchActionEnum::Delete => $this->isGranted(AbstractVoter::DELETE, $asset) ? null : self::REASON_FORBIDDEN,
            QuarantineBatchActionEnum::Merge => $this->checkMergeEligibility(
                $asset,
                $addAsVersion,
                $mergeTargets ?? $this->resolveMergeTargets($asset),
            ),
        };
    }

    /**
     * Picks the target of a merge as a new version: the requested one, or the
     * only valid target when there is no choice to make.
     *
     * @param list<MergeTarget> $mergeTargets
     *
     * @return Asset|string the target, or why none can be picked
     */
    public function resolveMergeTarget(array $mergeTargets, ?string $targetId): Asset|string
    {
        $valid = array_values(array_filter($mergeTargets, static fn (array $t): bool => $t['validTarget']));

        if (null !== $targetId) {
            foreach ($valid as $target) {
                if ($target['asset']->getId() === $targetId) {
                    return $target['asset'];
                }
            }

            return self::REASON_INVALID_TARGET;
        }

        return match (count($valid)) {
            0 => self::REASON_NO_VALID_TARGET,
            1 => $valid[0]['asset'],
            default => self::REASON_TARGET_REQUIRED,
        };
    }

    /**
     * A duplicate still pending or in quarantine may itself be rejected: it cannot stand for the incoming file.
     */
    private static function isAccepted(Asset $duplicate): bool
    {
        return AssetStatusEnum::Accepted === $duplicate->getStatus();
    }

    /**
     * @param list<MergeTarget> $mergeTargets
     */
    private function checkMergeEligibility(Asset $asset, bool $addAsVersion, array $mergeTargets): ?string
    {
        if (!$addAsVersion) {
            // The incoming file is deleted for good, the existing asset is left untouched
            if (!$this->isGranted(AbstractVoter::DELETE, $asset)) {
                return self::REASON_FORBIDDEN;
            }

            if (empty($mergeTargets)) {
                return self::REASON_NO_DUPLICATE;
            }

            // The incoming file is only redundant with a duplicate already let in
            return array_any($mergeTargets, static fn (array $t): bool => self::isAccepted($t['asset'])) ? null : self::REASON_NO_VALID_TARGET;
        }

        if (!$this->isGranted(AssetVoter::QUARANTINE_BYPASS, $asset)) {
            return self::REASON_FORBIDDEN;
        }
        if (empty($mergeTargets)) {
            return self::REASON_NO_DUPLICATE;
        }
        foreach ($mergeTargets as $target) {
            if ($target['validTarget']) {
                return null;
            }
        }

        return self::REASON_NO_VALID_TARGET;
    }
}
