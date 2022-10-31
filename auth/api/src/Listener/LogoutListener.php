<?php

declare(strict_types=1);

namespace App\Listener;

use App\Security\OAuthUserProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class LogoutListener implements LogoutSuccessHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private array $identityProviders;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        array $identityProviders
    )
    {
        $this->urlGenerator = $urlGenerator;
        $this->identityProviders = $identityProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function onLogoutSuccess(Request $request)
    {
        if (!($redirectUri = $request->query->get('r'))) {
            $redirectUri = $this->urlGenerator->generate('security_index');
        }

        $session = $request->getSession();
        if ($session && $session->has(OAuthUserProvider::AUTH_ORIGIN)) {
            $provider = $session->get(OAuthUserProvider::AUTH_ORIGIN);
            if ($provider) {
                $idp = array_values(array_filter($this->identityProviders, function (array $idp) use ($provider): bool {
                    return $idp['name'] === $provider;
                }));

                if (!empty($idp) && ($logoutUrl = $idp[0]['logout_url'] ?? false)) {
                    if ($redirectUriParam = $idp[0]['logout_redirect_param'] ?? false) {
                        $logoutUrl .= sprintf('%s%s=%s&client_id=%s',
                            strpos('?', $logoutUrl) > 0 ? '&' : '?',
                            $redirectUriParam,
                            urlencode($this->urlGenerator->generate('security_logout', [
                                'r' => $redirectUri,
                            ], UrlGeneratorInterface::ABSOLUTE_URL)),
                            $idp[0]['options']['client_id']
                        );
                    }

                    return new RedirectResponse($logoutUrl);
                }
            }
        }

        return new RedirectResponse($redirectUri);
    }
}
