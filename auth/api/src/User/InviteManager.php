<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;

class InviteManager
{
    public function __construct(private readonly int $allowedInviteDelay)
    {
    }

    public function userCanBeInvited(User $user): bool
    {
        return $user->canBeInvited($this->allowedInviteDelay);
    }

    public function getAllowedInviteDelay(): int
    {
        return $this->allowedInviteDelay;
    }
}
