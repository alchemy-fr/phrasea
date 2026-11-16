<?php

declare(strict_types=1);

namespace App\Security\Secrets;

/**
 * A secret cannot be decrypted by a process without the secret key: the API only holds the
 * public key, the workers decrypt the secrets.
 */
final class SecretKeyUnavailableException extends \RuntimeException
{
}
