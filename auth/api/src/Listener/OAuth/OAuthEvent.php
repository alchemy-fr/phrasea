<?php

declare(strict_types=1);

namespace App\Listener\OAuth;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Event;

class OAuthEvent extends Event
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
