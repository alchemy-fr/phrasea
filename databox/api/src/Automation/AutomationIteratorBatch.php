<?php

declare(strict_types=1);

namespace App\Automation;

use App\Entity\Core\Asset;

final readonly class AutomationIteratorBatch
{
    /**
     * @param Asset[] $assets the assets to process
     */
    public function __construct(
        public int $matchedCount,
        public int $newCount,
        public array $assets,
    ) {
    }
}
