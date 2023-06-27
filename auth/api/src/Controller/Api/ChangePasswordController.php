<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChangePasswordController extends AbstractController
{
    public function __construct(private readonly PasswordManager $passwordManager)
    {
    }

    #[Route(path: '/password/change', methods: ['POST'])]
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->passwordManager->changePassword($user, $request->request->get('old_password', ''), $request->request->get('new_password', ''));

        return new JsonResponse(true);
    }
}
