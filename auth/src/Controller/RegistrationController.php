<?php

declare(strict_types=1);

namespace App\Controller;

use App\Consumer\Handler\RegistrationHandler;
use App\Form\RegisterForm;
use App\User\UserManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="registration_")
 */
class RegistrationController extends AbstractController
{
    /**
     * @Route(path="/register", name="register")
     */
    public function registerAction(Request $request, UserManager $userManager, EventProducer $eventProducer)
    {
        $user = $userManager->createUser();
        $form = $this->createForm(RegisterForm::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $userManager->encodePassword($user);
            $userManager->persistUser($user);

            $eventProducer->publish(new EventMessage(RegistrationHandler::EVENT, [
                'id' => $user->getId(),
            ]));

            return $this->redirectToRoute('registration_unconfirmed');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/register/unconfirmed", name="unconfirmed", methods={"GET"})
     */
    public function unconfirmedAction()
    {
        return $this->render('registration/unconfirmed.html.twig');
    }

    /**
     * @Route(path="/register/confirm/{id}/{token}", name="confirm", methods={"GET"})
     */
    public function registerConfirmAction(string $id, string $token, UserManager $userManager)
    {
        $userManager->confirmEmail($id, $token);

        return $this->redirectToRoute('registration_confirmed');
    }

    /**
     * @Route(path="/register/confirmed", name="confirmed", methods={"GET"})
     */
    public function registerConfirmedAction()
    {
        return $this->render('registration/confirmed.html.twig', []);
    }
}
