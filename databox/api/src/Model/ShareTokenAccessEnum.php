<?php

declare(strict_types=1);

namespace App\Model;

/**
 * What the token of a share link opens (see ShareAccessManager::resolveTokenAccess()).
 */
enum ShareTokenAccessEnum
{
    /**
     * Unknown token, or share disabled, not started yet or expired.
     */
    case Denied;

    /**
     * Valid token of a protected share, without a valid access token.
     */
    case PasswordRequired;

    case Granted;
}
