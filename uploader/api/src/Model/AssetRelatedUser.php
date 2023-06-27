<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Security\Core\User\UserInterface;

class AssetRelatedUser implements UserInterface
{
    public function __construct(private readonly string $assetId, private readonly string $token)
    {
    }

    public function getId(): string
    {
        return $this->token;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getPassword()
    {
        return null;
    }

    public function getSalt()
    {
        return null;
    }

    public function getUsername()
    {
        return $this->assetId;
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->assetId;
    }
}
