<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\User\UserManager;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController;

class AdminController extends EasyAdminController
{
    /**
     * @var UserManager
     */
    private $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    protected function createNewEntity()
    {
        $entityFullyQualifiedClassName = $this->entity['class'];
        if (User::class === $entityFullyQualifiedClassName) {
            return $this->userManager->createUser();
        }

        return new $entityFullyQualifiedClassName();
    }
}
