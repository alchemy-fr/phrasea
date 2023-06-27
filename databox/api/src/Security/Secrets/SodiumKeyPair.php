<?php

declare(strict_types=1);

namespace App\Security\Secrets;

final readonly class SodiumKeyPair
{
    public function __construct(
        private string $public,
        private string $secret,
    ) {
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
