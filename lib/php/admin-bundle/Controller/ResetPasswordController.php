<?php

namespace Alchemy\AdminBundle\Controller;

use Alchemy\AdminBundle\Form\RequestResetPasswordForm;
use Alchemy\RemoteAuthBundle\Client\AuthServiceClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/reset-password', name: 'reset_password_')]
class ResetPasswordController extends AbstractAdminController
{
    #[Route(path: '/request', name: 'request')]
    public function requestResetPassword(Request $request, AuthServiceClient $authServiceClient): Response
    {
        $form = $this->createForm(RequestResetPasswordForm::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $authServiceClient->post('/en/password/reset-request', [
                'json' => [
                    'username' => $form->get('email')->getData(),
                ],
            ]);

            return $this->redirectToRoute('alchemy_admin_reset_password_requested');
        }

        return $this->render(
            '@AlchemyAdmin/reset_password/request.html.twig',
            array_merge($this->getLayoutParams(), [
            'form' => $form->createView(),
        ]));
    }

    #[Route(path: '/requested', name: 'requested')]
    public function requestedResetPassword(): Response
    {
        return $this->render(
            '@AlchemyAdmin/reset_password/requested.html.twig',
            $this->adminConfigRegistry->getLayoutParams(),
        );
    }
}
