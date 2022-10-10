<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Security;

use Alchemy\AdminBundle\Auth\OAuthClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

class LogoutSuccessHandler implements LogoutSuccessHandlerInterface
{
    private OAuthClient $client;

    public function __construct(OAuthClient $client)
    {
        $this->client = $client;
    }

    public function onLogoutSuccess(Request $request)
    {
        $redirectUri = $request->headers->get('referer', $request->getUriForPath('/'));

        return new RedirectResponse($this->client->getLogoutUrl().'?r='.urlencode($redirectUri));
    }
}
