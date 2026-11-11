<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Controller;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * OAuth 2.0 Protected Resource Metadata (RFC 9728) of the MCP endpoint, from which MCP clients
 * discover the Keycloak realm to get a token from.
 */
#[AsController]
final readonly class OAuthProtectedResourceAction
{
    final public const string ROUTE_NAME = 'alchemy_mcp_oauth_protected_resource';

    public function __construct(
        private KeycloakUrlGenerator $keycloakUrlGenerator,
        private UrlGeneratorInterface $urlGenerator,
        private string $serverName,
        private string $resourceName,
    ) {
    }

    // The variant suffixed with the path of the resource (RFC 9728 §3.1) is the one MCP clients try first.
    #[Route(path: '/.well-known/oauth-protected-resource', name: self::ROUTE_NAME, methods: ['GET'])]
    #[Route(path: '/.well-known/oauth-protected-resource/mcp', name: self::ROUTE_NAME.'_mcp', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'resource' => $this->urlGenerator->generate('_mcp_endpoint_'.$this->serverName, [], UrlGeneratorInterface::ABSOLUTE_URL),
            'authorization_servers' => [
                $this->keycloakUrlGenerator->getRealmInfoUrl(false),
            ],
            'bearer_methods_supported' => ['header'],
            'resource_name' => $this->resourceName,
        ]);
    }
}
