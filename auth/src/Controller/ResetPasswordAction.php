<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ResetPasswordAction extends AbstractController
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
     * @Route(path="/reset-password")
     */
    public function __invoke(Request $request)
    {
        $this->resetPasswordManager->requestPasswordResetForLogin($request->request->get('username'));

        return new JsonResponse(true);
    }
}
