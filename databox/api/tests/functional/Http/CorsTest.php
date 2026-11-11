<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CorsTest extends AbstractDataboxTestCase
{
    private const string CLIENT_ORIGIN = 'https://databox.test';
    private const string DASHBOARD_ORIGIN = 'https://dashboard.test';
    private const string OTHER_ORIGIN = 'https://attacker.example';

    public function testApiIsOpenToTheClient(): void
    {
        $response = $this->preflight('/assets', 'POST', self::CLIENT_ORIGIN);
        $this->assertSame([self::CLIENT_ORIGIN], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
        $this->assertStringContainsStringIgnoringCase('x-data-locale', $response->getHeaders(false)['access-control-allow-headers'][0] ?? '');

        $response = $this->get('/locales', self::CLIENT_ORIGIN);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([self::CLIENT_ORIGIN], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
    }

    public function testApiIsOpenToTheDashboard(): void
    {
        $response = $this->preflight('/permissions/groups', 'GET', self::DASHBOARD_ORIGIN);
        $this->assertSame([self::DASHBOARD_ORIGIN], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
    }

    public function testApiIsClosedToOtherOrigins(): void
    {
        $response = $this->preflight('/assets', 'POST', self::OTHER_ORIGIN);
        $this->assertArrayNotHasKey('access-control-allow-origin', $response->getHeaders(false));

        $response = $this->get('/locales', self::OTHER_ORIGIN);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('access-control-allow-origin', $response->getHeaders(false));
    }

    private function preflight(string $uri, string $method, string $origin): ResponseInterface
    {
        return static::createClient()->request('OPTIONS', $uri, [
            'headers' => [
                'Origin' => $origin,
                'Access-Control-Request-Method' => $method,
                'Access-Control-Request-Headers' => 'authorization, content-type, x-data-locale',
            ],
        ]);
    }

    private function get(string $uri, string $origin): ResponseInterface
    {
        return static::createClient()->request('GET', $uri, [
            'headers' => [
                'Origin' => $origin,
            ],
        ]);
    }
}
