<?php

declare(strict_types=1);

namespace App\Controller;

use Alchemy\ReportBundle\ReportUserService;
use App\Form\RequestPasswordResetForm;
use App\Report\AuthLogActionInterface;
use App\Security\PasswordManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/{_locale}/security/password-reset", name="password_reset_")
 */
class RequestResetPasswordController extends AbstractController
{
    private PasswordManager $resetPasswordManager;

    public function __construct(PasswordManager $resetPasswordManager)
    {
        $this->resetPasswordManager = $resetPasswordManager;
    }

    /**
     * @Route(path="/request", name="request", methods={"GET", "POST"})
     */
    public function requestAction(Request $request, ReportUserService $reportClient)
    {
        $form = $this->createForm(RequestPasswordResetForm::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $username = $form->get('username')->getData();

            $this->resetPasswordManager->requestPasswordResetForLogin(
                $username,
                $request->getLocale() ?? $request->getDefaultLocale() ?? 'en'
            );

            $reportClient->pushHttpRequestLog(
                $request,
                AuthLogActionInterface::REQUEST_RESET_PASSWORD,
                null,
                [
                    'username' => $username,
                ],
            );

            return $this->redirectToRoute('password_reset_requested');
        }

        return $this->render('security/password_reset_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/requested", name="requested", methods={"GET"})
     */
    public function requestedAction()
    {
        return $this->render('security/password_reset_requested.html.twig');
    }
}
