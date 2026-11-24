<?php

declare(strict_types=1);

namespace App\Consumer;

/**
 * Whether the current processing comes from a bulk processing (a message consumed
 * from the "task", "bulk", "bulk_heavy" or "bulk_heavy_low" transport): the workflow
 * jobs it triggers then go to the bulk transports.
 */
final class BulkOrigin
{
    private int $depth = 0;

    public function enter(): void
    {
        ++$this->depth;
    }

    public function leave(): void
    {
        if ($this->depth > 0) {
            --$this->depth;
        }
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }
}
