<?php

namespace App\Security;

final readonly class PasswordTokenExtractor
{
    private const HEADER_NAME = 'X-Passwords';

    public function __construct()
    {
    }

    public function getPasswords(): array
    {

    }
}
