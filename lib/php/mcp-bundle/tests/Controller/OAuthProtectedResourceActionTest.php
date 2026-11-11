<?php

declare(strict_types=1);

namespace Alchemy\McpBundle\Tests\Controller;

use Alchemy\AuthBundle\Client\KeycloakUrlGenerator;
use Alchemy\McpBundle\Controller\OAuthProtectedResourceAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthProtectedResourceActionTest extends TestCase
{
    public function testMetadataNamesPublicKeycloakRealm(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->with('_mcp_endpoint_expose', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://api-expose.example.com/mcp');

        // The internal URL is only reachable from the containers: clients must get the public one.
        $keycloak = new KeycloakUrlGenerator('https://keycloak.example.com', 'http://keycloak:8080', 'phrasea');

        $response = (new OAuthProtectedResourceAction($keycloak, $urlGenerator, 'expose', 'Phrasea Expose'))();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'resource' => 'https://api-expose.example.com/mcp',
            'authorization_servers' => ['https://keycloak.example.com/realms/phrasea'],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Phrasea Expose',
        ], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
