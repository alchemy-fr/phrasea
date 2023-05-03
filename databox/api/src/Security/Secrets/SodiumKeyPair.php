<?php

declare(strict_types=1);

namespace App\Security\Secrets;

final class SodiumKeyPair
{
    public function __construct(
        private readonly string $public,
        private readonly string $secret,
    )
    {
    }

    public function getPublic(): string
    {
        return $this->public;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

}
