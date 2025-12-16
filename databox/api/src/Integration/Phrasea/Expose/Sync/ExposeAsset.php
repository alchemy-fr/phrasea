<?php

namespace App\Integration\Phrasea\Expose\Sync;

final readonly class ExposeAsset
{
    public function __construct(
        public string $id,
        public string $basketAssetId,
        public string $fileId,
        /**
         * @var array<ExposeSubDefinition>
         */
        public array $subDefinitions,
    ) {
    }
}
