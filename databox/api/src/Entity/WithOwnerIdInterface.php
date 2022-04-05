<?php

declare(strict_types=1);

namespace App\Entity;

interface WithOwnerIdInterface
{
    public function getOwnerId(): ?string;

    public function setOwnerId(?string $ownerId): void;
}
