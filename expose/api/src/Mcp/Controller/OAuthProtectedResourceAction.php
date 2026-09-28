<?php

declare(strict_types=1);

namespace App\Mcp\Controller;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * OAuth 2.0 Protected Resource Metadata (RFC 9728) of the MCP endpoint.
 */
#[AsController]
final readonly class OAuthProtectedResourceAction
{
    final public const string ROUTE_NAME = 'mcp_oauth_protected_resource';

    public function __construct(
        private KeycloakUrlGenerator $keycloakUrlGenerator,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/.well-known/oauth-protected-resource', name: self::ROUTE_NAME, methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'resource' => $this->urlGenerator->generate('_mcp_endpoint_expose', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'authorization_servers' => [
                $this->keycloakUrlGenerator->getRealmInfoUrl(false),
            ],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Phrasea Expose',
        ]);
    }
}
