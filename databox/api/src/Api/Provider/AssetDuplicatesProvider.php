<?php

declare(strict_types=1);

namespace App\Api\Provider;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Model\Output\AssetDuplicateOutput;
use App\Entity\Core\Asset;
use App\Repository\Core\AssetRepository;
use App\Security\Voter\AbstractVoter;
use App\Service\Asset\DuplicateAssetResolver;

/**
 * Resolves the duplicates of a quarantined asset's source file into the actual
 * duplicate assets, so the client "merge" flow can display them and let the
 * user pick which one to keep.
 */
final class AssetDuplicatesProvider implements ProviderInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly DuplicateAssetResolver $duplicateAssetResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AssetDuplicateOutput
    {
        $asset = $this->assetRepository->find($uriVariables['id']);
        if (!$asset instanceof Asset) {
            return null;
        }
        $this->denyAccessUnlessGranted(AbstractVoter::READ, $asset);

        $source = $asset->getSource();
        if (null === $source) {
            return new AssetDuplicateOutput([]);
        }

        return new AssetDuplicateOutput(
            $this->duplicateAssetResolver->resolveDuplicates($source, excludeAssetId: $asset->getId()),
        );
    }
}
