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
         * @var array<int, array{id: string, title: ?string, thumbnail: ?array{id: string, url: string, type: ?string}, sourceType: ?string, createdAt: ?string, analyzers: string[]}>
         */
        #[Groups(['_'])]
        public array $duplicates,
    ) {
    }
}
