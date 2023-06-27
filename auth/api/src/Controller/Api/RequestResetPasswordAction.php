<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RequestResetPasswordAction extends AbstractController
{
    public function __construct(private readonly PasswordManager $passwordManager)
    {
    }

    #[Route(path: '/{_locale}/password-reset/request', methods: ['POST'])]
    public function __invoke(Request $request)
    {
        $this->passwordManager->requestPasswordResetForLogin(
            $request->request->get('username'),
            $request->getLocale() ?? $request->getDefaultLocale() ?? 'en'
        );

        return new JsonResponse(true);
    }
}
