<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Entity\User;
use App\Form\ChangePasswordForm;
use App\Report\AuthLogActionInterface;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/{_locale}/security/password", name="security_password_")
 */
class ChangePasswordController extends AbstractController
{
    private PasswordManager $passwordManager;

    public function __construct(PasswordManager $passwordManager)
    {
        $this->passwordManager = $passwordManager;
    }

    /**
     * @Route(path="/change", name="change", methods={"GET", "POST"})
     */
    public function changeAction(Request $request, ReportUserService $reportClient)
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordForm::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $oldPassword = $form->get('old_password')->getData();
            $newPassword = $form->get('new_password')->getData();

            $this->passwordManager->changePassword(
                $user,
                $oldPassword,
                $newPassword
            );

            $reportClient->pushHttpRequestLog(
                $request,
                AuthLogActionInterface::CHANGE_PASSWORD,
                $user->getId(), [
                    'username' => $user->getUsername(),
                ]
            );

            $this->addFlash(
                'notice',
                'Your password has been changed!'
            );

            return $this->redirectToRoute('security_index');
        }

        return $this->render('security/password_change.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
