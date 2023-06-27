<?php

namespace App\Controller;

use App\Entity\User;
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
    public const SESSION_REDIRECT_KEY = 'auth.redirect_uri';

    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->render('security/index.html.twig');
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $authenticationUtils,
        array $identityProviders,
        array $loginFormLayout,
        Request $request
    ): Response {
        $session = $request->getSession();

        $redirectUri = $request->get('r');
        if (null === $redirectUri) {
            $redirectUri = $session->get(self::SESSION_REDIRECT_KEY);
            $redirectUri ??= $this->generateUrl('security_index');
        } else {
            $session->set(self::SESSION_REDIRECT_KEY, $redirectUri);
        }

        if ($this->getUser() instanceof User) {
            return $this->redirect($redirectUri);
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($connect = $request->query->get('connect')) {
            $idp = array_values(array_filter($identityProviders, function (array $idp) use ($connect): bool {
                return $idp['name'] === $connect;
            }));

            if (!empty($idp)) {
                return $this->redirect($this->generateUrl(sprintf('%s_entrypoint', $idp[0]['type']), [
                    'provider' => $idp[0]['name'],
                    'redirect_uri' => $redirectUri,
                ]));
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'externalIdpOnTop' => $loginFormLayout['externalIdpOnTop'] ?? false,
            'displayIdPTitle' => $loginFormLayout['displayIdPTitle'] ?? true,
            'providers' => array_map(function (array $idp) use ($redirectUri): array {
                return array_merge($idp, [
                    'entrypoint' => $this->generateUrl(sprintf('%s_entrypoint', $idp['type']), [
                        'provider' => $idp['name'],
                        'redirect_uri' => $redirectUri,
                    ]),
                ]);
            }, $identityProviders),
        ]);
    }
}
