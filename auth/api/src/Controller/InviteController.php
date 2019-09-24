<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\SetPasswordForm;
use App\Security\PasswordManager;
use App\User\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/{_locale}/register/invite", name="invite_")
 */
class InviteController extends AbstractController
{
    /**
     * @Route(path="/confirm/{id}/{token}", name="confirm", methods={"GET", "POST"})
     */
    public function inviteConfirmAction(
        string $id,
        string $token,
        Request $request,
        UserManager $userManager,
        PasswordManager $passwordManager
    ) {
        $user = $userManager->getUserByIdAndToken($id, $token);

        if (!$user->hasPassword()) {
            $form = $this->createForm(SetPasswordForm::class);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var User $data */
                $data = $form->getData();
                $passwordManager->definePassword($user, $data->getPlainPassword());
            } else {
                return $this->render('invite/set_password.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
        }

        $userManager->confirmEmail($user);

        return $this->redirectToRoute('invite_confirmed');
    }

    /**
     * @Route(path="/confirmed", name="confirmed", methods={"GET"})
     */
    public function registerConfirmedAction()
    {
        return $this->render('invite/confirmed.html.twig', []);
    }
}
