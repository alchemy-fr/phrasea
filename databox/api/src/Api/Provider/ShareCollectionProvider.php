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
    ): array|object {

        $asset = $this->getAsset($context);

        $criteria = [
            'asset' => $asset->getId(),
        ];

        return array_map(function (Share $share): Share {
            return $this->shareReadProvider->provideShare($share);
        }, $this->em->getRepository(Share::class)->findBy($criteria, [
            'createdAt' => 'DESC',
        ]));
    }
}
