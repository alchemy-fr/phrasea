<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChangePasswordAction extends AbstractController
{
    /**
     * @var PasswordManager
     */
    private $resetPasswordManager;

    public function __construct(PasswordManager $resetPasswordManager)
    {
        $this->resetPasswordManager = $resetPasswordManager;
    }

    /**
     * @Route(path="/password/change", methods={"POST"})
     */
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->resetPasswordManager->changePassword($user, $request->request->get('password'), $request->request->get('new_password'));

        return new JsonResponse(true);
    }
}
