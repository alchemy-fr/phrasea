<?php

declare(strict_types=1);

namespace App\Audit;

/**
 * Leaves a field of an auditable entity out of the audited changes,
 * or, with $mask, logs that it changed without its values.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class AuditIgnore
{
    final public const string MASK = '***';

    public function __construct(
        public bool $mask = false,
    ) {
    }
}
