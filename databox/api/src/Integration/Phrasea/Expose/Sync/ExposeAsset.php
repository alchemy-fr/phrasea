<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose\Sync;

/**
 * An asset of an Expose publication, as published from Databox.
 */
final readonly class ExposeAsset
{
    public function __construct(
        public string $id,
        /** What the publisher matches the asset by (a basket item, an asset of an automation…) */
        public string $key,
        public string $fileId,
        public ?string $title,
        public ?string $description,
        public array $translations,
        /**
         * @var array<ExposeSubDefinition>
         */
        public array $subDefinitions,
        /** Hash of the clip the published file was cut to */
        public ?string $clipHash = null,
    ) {
    }
}
