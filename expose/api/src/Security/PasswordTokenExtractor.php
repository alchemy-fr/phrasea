<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class PasswordTokenExtractor
{
    private const string HEADER_NAME = 'X-Passwords';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    private function getPasswords(): array
    {
        $passwords = $this->requestStack->getCurrentRequest()?->headers->get(self::HEADER_NAME);
        if (empty($passwords)) {
            return [];
        }

        try {
            $passwords = json_decode(base64_decode($passwords), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($passwords) ? $passwords : [];
    }

    public function getPublicationPassword(string $publicationId): ?string
    {
        $password = $this->getPasswords()[$publicationId] ?? null;

        return is_string($password) ? $password : null;
    }
}
