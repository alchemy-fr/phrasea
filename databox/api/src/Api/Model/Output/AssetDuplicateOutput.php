<?php

declare(strict_types=1);

namespace App\Api\Model\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * List of assets detected as duplicates of a quarantined asset, used by the
 * quarantine "merge" resolution flow to let the user pick which one to keep.
 */
final readonly class AssetDuplicateOutput
{
    public function __construct(
        /**
         * Each duplicate asset is serialized through the standard asset
         * normalization (like GET /assets/{id}).
         *
         * @var array<int, array{asset: \App\Entity\Core\Asset, analyzers: string[]}>
         */
        #[Groups(['_'])]
        public array $duplicates,
    ) {
    }
}
