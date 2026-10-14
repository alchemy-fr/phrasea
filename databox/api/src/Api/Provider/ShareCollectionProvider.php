<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Share;
use App\Security\Voter\AbstractVoter;

final class ShareCollectionProvider extends AbstractAssetFilteredCollectionProvider
{
    public function __construct(
        private readonly ShareReadProvider $shareReadProvider,
    ) {
    }

    protected function provideCollection(
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): array {

        $asset = $this->getAsset($operation);

        // Reading the asset is not enough: only the shares the user may manage
        // (his own, or those whose every asset he can share) are listed, as
        // they expose their token.
        $shares = array_filter(
            $this->em->getRepository(Share::class)->getSharesOfAssets([$asset->getId()]),
            fn (Share $share): bool => $this->isGranted(AbstractVoter::READ, $share),
        );

        return array_map($this->shareReadProvider->provideShare(...), array_values($shares));
    }
}
