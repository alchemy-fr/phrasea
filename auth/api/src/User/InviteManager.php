<?php

declare(strict_types=1);

namespace App\User;

use App\Entity\User;

class InviteManager
{
    private int $allowedInviteDelay;

    public function __construct(int $allowedInviteDelay)
    {
        $this->allowedInviteDelay = $allowedInviteDelay;
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
