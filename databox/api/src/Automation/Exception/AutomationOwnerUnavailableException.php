<?php

declare(strict_types=1);

namespace App\Automation\Exception;

/**
 * The user an automation acts as cannot be resolved (missing or disabled in the IdP).
 */
final class AutomationOwnerUnavailableException extends \RuntimeException
{
}
