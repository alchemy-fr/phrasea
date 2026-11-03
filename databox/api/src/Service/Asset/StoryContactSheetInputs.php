<?php

declare(strict_types=1);

namespace App\Service\Asset;

use App\Entity\Core\File;

final readonly class StoryContactSheetInputs
{
    public function __construct(
        /**
         * The images to assemble, in the story order.
         *
         * @var File[]
         */
        public array $files,
        /**
         * Whether an item has a source but not yet the expected rendition.
         */
        public bool $pending,
    ) {
    }
}
