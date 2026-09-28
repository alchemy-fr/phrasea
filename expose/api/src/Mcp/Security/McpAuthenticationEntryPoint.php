<?php

declare(strict_types=1);

namespace App\Mcp\Security;

use App\Mcp\Controller\OAuthProtectedResourceAction;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Answers unauthenticated MCP requests the way the MCP authorization spec expects: a 401 whose
 * WWW-Authenticate header points to the protected resource metadata (RFC 9728), from which the
 * client discovers the Keycloak realm to get a token from.
 */
final readonly class McpAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $metadataUrl = $this->urlGenerator->generate(OAuthProtectedResourceAction::ROUTE_NAME, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => sprintf('Bearer resource_metadata="%s"', $metadataUrl),
        ]);
    }
}
