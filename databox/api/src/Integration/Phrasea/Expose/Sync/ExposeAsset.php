<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose\Sync;

final readonly class ExposeAsset
{
    public function __construct(
        public string $id,
        public string $basketAssetId,
        public string $fileId,
        public ?string $name,
        public ?string $description,
        public array $translations,
        /**
         * @var array<ExposeSubDefinition>
         */
        public array $subDefinitions,
    ) {
    }
}
