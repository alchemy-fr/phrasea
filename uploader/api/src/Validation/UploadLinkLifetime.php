<?php

declare(strict_types=1);

namespace App\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * An enabled upload link expires within the maximum lifetime of the links (UPLOAD_LINK_MAX_TTL, in days).
 *
 * A disabled link is not checked: its creator must always be able to disable it, even once the maximum
 * has been lowered below what it was given.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UploadLinkLifetime extends Constraint
{
    public string $message = 'An upload link cannot last more than {{ days }} days.';

    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
