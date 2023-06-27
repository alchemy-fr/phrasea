<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\User;
use App\Form\ResetPasswordForm;
use App\Report\AuthLogActionInterface;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/{_locale}/security/password-reset', name: 'password_reset_')]
class ResetPasswordAction extends AbstractController
{
    public function __construct(private readonly PasswordManager $passwordManager, private readonly ReportUserService $reportClient)
    {
    }

    #[Route(path: '/{id}/{token}', name: 'reset', methods: ['GET', 'POST'])]
    public function reset(string $id, string $token, Request $request)
    {
        $passwordRequest = $this->passwordManager->getResetRequest($id, $token);

        $form = $this->createForm(ResetPasswordForm::class, $passwordRequest->getUser());

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();
            $this->passwordManager->resetPassword($id, $token, $user->getPlainPassword());

            $this->reportClient->pushHttpRequestLog(
                $request,
                AuthLogActionInterface::RESET_PASSWORD,
                $user->getId(), [
                    'username' => $user->getUsername(),
                ]
            );

            return $this->redirectToRoute('password_reset_changed');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/changed', name: 'changed', methods: ['GET'])]
    public function changed()
    {
        return $this->render('security/password_changed.html.twig');
    }
}
