<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * @Route("/{_locale}/security", name="security_")
 */
class SecurityController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('security/index.html.twig');
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(string $_locale, AuthenticationUtils $authenticationUtils, Request $request): Response
    {
         if ($this->getUser()) {
             return $this->redirectToRoute('app_index');
         }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $providers = [];

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'providers' => $providers,
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
