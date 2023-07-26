<?php

declare(strict_types=1);

namespace Alchemy\AuthBundle\Controller;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Route(path: '/oauth/v2', name: 'oauth_proxy_')]
class OAuthProxyController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $keycloakClient,
        private KeycloakUrlGenerator $urlGenerator,
    ) {
    }

    #[Route(path: '/token', name: 'token', methods: ['POST'])]
    public function tokenAction(Request $request): Response
    {
        $response = $this->keycloakClient->request('POST', $this->urlGenerator->getTokenUrl(), [
            'body' => $request->request->all(),
        ]);

        assert($response instanceof ResponseInterface);

        return new Response($response->getContent(false), $response->getStatusCode(), $response->getHeaders(false));
    }
}
