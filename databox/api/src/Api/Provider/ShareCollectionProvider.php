<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Core\Share;

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

        $asset = $this->getAsset($context);

        return array_map($this->shareReadProvider->provideShare(...), $this->em->getRepository(Share::class)->getSharesOfAssets([$asset->getId()]));
    }
}
