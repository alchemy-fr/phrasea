<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

class PasswordToken extends AbstractToken
{
    public function __construct(private readonly string $passwords)
    {
        parent::__construct();
    }

    public function getCredentials()
    {
        return $this->getPasswords();
    }

    public function getPasswords(): string
    {
        return $this->passwords;
    }

    public function getPublicationPassword(string $publicationId): ?string
    {
        try {
            $passwords = json_decode(base64_decode($this->passwords), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return $passwords[$publicationId] ?? null;
    }
}
