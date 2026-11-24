<?php

declare(strict_types=1);

namespace App\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * The URL a target is notified at receives its authorization key: https only.
 *
 * http is accepted for a host of the stack (a name without dot, as "databox-api") and in the dev and test
 * environments.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SecureTargetUrl extends Constraint
{
    public string $message = 'The target URL must use https: the authorization key is sent to it with each upload. http is only accepted for a host of the stack, without dot (e.g. "http://databox-api/…").';

    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }
}
