<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CorsTest extends AbstractUploaderTestCase
{
    private const string CLIENT_ORIGIN = 'https://uploader.test';
    private const string OTHER_ORIGIN = 'https://attacker.example';

    public function testApiIsOpenToTheClient(): void
    {
        $response = $this->preflight('/targets', 'POST', self::CLIENT_ORIGIN);
        $this->assertSame([self::CLIENT_ORIGIN], $response->getHeaders(false)['access-control-allow-origin'] ?? null);

        $response = $this->get('/targets', self::CLIENT_ORIGIN);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([self::CLIENT_ORIGIN], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
    }

    public function testApiIsClosedToOtherOrigins(): void
    {
        $response = $this->preflight('/targets', 'POST', self::OTHER_ORIGIN);
        $this->assertArrayNotHasKey('access-control-allow-origin', $response->getHeaders(false));

        $response = $this->get('/targets', self::OTHER_ORIGIN);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('access-control-allow-origin', $response->getHeaders(false));
    }

    private function preflight(string $uri, string $method, string $origin): ResponseInterface
    {
        return static::createClient()->request('OPTIONS', $uri, [
            'headers' => [
                'Origin' => $origin,
                'Access-Control-Request-Method' => $method,
                'Access-Control-Request-Headers' => 'authorization, content-type',
            ],
        ]);
    }

    private function get(string $uri, string $origin): ResponseInterface
    {
        return static::createClient()->request('GET', $uri, [
            'headers' => [
                'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID),
                'Origin' => $origin,
            ],
        ]);
    }
}
