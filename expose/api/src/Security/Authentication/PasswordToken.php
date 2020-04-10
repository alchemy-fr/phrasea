<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Throwable;

class PasswordToken extends AbstractToken
{
    private string $passwords;

    public function __construct(string $passwords)
    {
        parent::__construct();
        $this->passwords = $passwords;
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
            $passwords = json_decode(base64_decode($this->passwords), true);
        } catch (Throwable $e) {
            return null;
        }

        return $passwords[$publicationId] ?? null;
    }
}
