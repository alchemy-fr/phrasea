<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordForm;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @Route(path="/password/reset/{id}/{token}", name="reset_password", methods={"GET", "POST"})
     */
    public function reset(string $id, string $token, Request $request)
    {
        $this->resetPasswordManager->getResetRequest($id, $token);

        $form = $this->createForm(ResetPasswordForm::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('new_password')->getData();
            $this->resetPasswordManager->resetPassword($id, $token, $newPassword);

            return $this->redirectToRoute('reset_password_changed');
        }

        return $this->render('password/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/password/reset/changed", name="reset_password_changed", methods={"GET"})
     */
    public function changed()
    {
        return $this->render('password/password_changed.html.twig');
    }
}
