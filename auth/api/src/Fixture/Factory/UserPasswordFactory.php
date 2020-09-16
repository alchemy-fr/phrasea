<?php

namespace App\Fixture\Factory;

use App\Entity\User;
use App\User\UserManager;

class UserPasswordFactory
{
    /**
     * @var UserManager
     */
    private $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public function create(string $password): User
    {
        $user = $this->userManager->createUser();
        $user->setPlainPassword($password);
        $this->userManager->encodePassword($user);

        return $user;
    }
}
