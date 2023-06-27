<?php

namespace App\Fixture\Factory;

use App\Entity\User;
use App\User\UserManager;

class UserPasswordFactory
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    public function create(string $password): User
    {
        $user = $this->userManager->createUser();
        $user->setPlainPassword($password);
        $this->userManager->encodePassword($user);

        return $user;
    }
}
