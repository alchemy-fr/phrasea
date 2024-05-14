<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class PasswordTokenExtractor
{
    private const HEADER_NAME = 'X-Passwords';

    public function __construct(
        private RequestStack $requestStack,
    )
    {
    }

    private function getPasswords(): array
    {
        $passwords = $this->requestStack->getCurrentRequest()?->headers->get(self::HEADER_NAME);
        if (empty($passwords)) {
            return [];
        }

        try {
            return json_decode(base64_decode($passwords), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getPublicationPassword(string $publicationId): ?string
    {
        return $this->getPasswords()[$publicationId] ?? null;
    }
}
