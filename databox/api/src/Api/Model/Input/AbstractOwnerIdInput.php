<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AbstractOwnerIdInput
{
    public ?string $ownerId = null;

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): void
    {
        $this->ownerId = $ownerId;
    }
}
