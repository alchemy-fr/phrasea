<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Tests\Security;

use Alchemy\McpBundle\Controller\OAuthProtectedResourceAction;
use Alchemy\McpBundle\Security\McpAuthenticationEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class McpAuthenticationEntryPointTest extends TestCase
{
    public function testUnauthorizedResponsePointsToResourceMetadata(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(OAuthProtectedResourceAction::ROUTE_NAME, [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://api-expose.example.com/.well-known/oauth-protected-resource');

        $response = (new McpAuthenticationEntryPoint($urlGenerator))->start(Request::create('/mcp', 'POST'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            'Bearer resource_metadata="https://api-expose.example.com/.well-known/oauth-protected-resource"',
            $response->headers->get('WWW-Authenticate'),
        );
        $this->assertSame(['message' => 'Authentication required'], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
