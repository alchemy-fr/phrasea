<?php

declare(strict_types=1);

namespace App\Listener\OAuth;

use App\Entity\User;

class OAuthEvent
{
    /**
     * @var User|null
     */
    private $user;

    public function __construct(?User $user)
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
