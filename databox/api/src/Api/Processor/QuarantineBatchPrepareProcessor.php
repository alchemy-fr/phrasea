<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\Input\QuarantineBatchPrepareInput;
use App\Api\Model\Output\QuarantineBatchPrepareOutput;
use App\Entity\Core\Asset;
use App\Model\QuarantineBatchActionEnum;
use App\Repository\Core\AssetRepository;
use App\Service\Asset\QuarantineResolver;
use Ramsey\Uuid\Uuid;

/**
 * Tells, for each selected quarantined asset, which batch action it is
 * eligible to. An unknown or unreadable asset is reported as such rather than
 * failing the whole request.
 */
final readonly class QuarantineBatchPrepareProcessor implements ProcessorInterface
{
    public function __construct(
        private AssetRepository $assetRepository,
        private QuarantineResolver $quarantineResolver,
    ) {
    }

    /**
     * @param QuarantineBatchPrepareInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): QuarantineBatchPrepareOutput
    {
        $ids = array_values(array_unique(array_map(strval(...), $data->ids)));

        $assets = [];
        foreach ($this->assetRepository->findByIds(array_values(array_filter($ids, Uuid::isValid(...)))) as $asset) {
            $assets[$asset->getId()] = $asset;
        }

        return new QuarantineBatchPrepareOutput(array_map(
            fn (string $id): array => $this->prepareItem($id, $assets[strtolower($id)] ?? null),
            $ids,
        ));
    }

    private function prepareItem(string $id, ?Asset $asset): array
    {
        // Duplicates of an asset the user cannot resolve are none of their business
        $mergeTargets = null !== $asset && null === $this->quarantineResolver->checkQuarantined($asset)
            ? $this->quarantineResolver->resolveMergeTargets($asset)
            : [];

        $checks = [
            'bypass' => $this->quarantineResolver->checkEligibility($asset, QuarantineBatchActionEnum::Bypass),
            'trash' => $this->quarantineResolver->checkEligibility($asset, QuarantineBatchActionEnum::Trash),
            'delete' => $this->quarantineResolver->checkEligibility($asset, QuarantineBatchActionEnum::Delete),
            'mergeAsVersion' => $this->quarantineResolver->checkEligibility($asset, QuarantineBatchActionEnum::Merge, true, $mergeTargets),
            'mergeDiscard' => $this->quarantineResolver->checkEligibility($asset, QuarantineBatchActionEnum::Merge, false, $mergeTargets),
        ];

        return [
            'id' => $id,
            ...array_map(static fn (?string $reason): bool => null === $reason, $checks),
            'reasons' => array_filter($checks),
            'duplicates' => $mergeTargets,
        ];
    }
}
