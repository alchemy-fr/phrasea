<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose;

/**
 * Expose refused to create an asset (e.g. a file type it does not accept): only this asset
 * cannot be published, the others can.
 */
final class ExposeAssetRefusedException extends \RuntimeException
{
    public function __construct(
        public readonly string $title,
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Expose refused the asset "%s": %s', $title, $reason), 0, $previous);
    }
}
