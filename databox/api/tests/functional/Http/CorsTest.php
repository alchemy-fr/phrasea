<?php

declare(strict_types=1);

namespace App\Tests\Functional\Http;

use Alchemy\CoreBundle\Env\OriginEnvVarProcessor;
use App\Tests\Functional\AbstractDataboxTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CorsTest extends AbstractDataboxTestCase
{
    private const string OTHER_ORIGIN = 'https://attacker.example';

    public function testApiIsOpenToTheClient(): void
    {
        $response = $this->preflight('/assets', 'POST', self::clientOrigin());
        $this->assertSame([self::clientOrigin()], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
        $this->assertStringContainsStringIgnoringCase('x-data-locale', $response->getHeaders(false)['access-control-allow-headers'][0] ?? '');

        $response = $this->get('/locales', self::clientOrigin());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([self::clientOrigin()], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
    }

    public function testApiIsOpenToTheDashboard(): void
    {
        $response = $this->preflight('/permissions/groups', 'GET', self::dashboardOrigin());
        $this->assertSame([self::dashboardOrigin()], $response->getHeaders(false)['access-control-allow-origin'] ?? null);
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

    private static function clientOrigin(): string
    {
        return self::configuredOrigin('DATABOX_CLIENT_URL');
    }

    private static function dashboardOrigin(): string
    {
        return self::configuredOrigin('DASHBOARD_CLIENT_URL');
    }

    /**
     * The origin allowed by the API: the one of the client URL it is configured with, which the
     * environment of the container overrides (docker compose sets it to the URL of the stack).
     */
    private static function configuredOrigin(string $envName): string
    {
        $origin = OriginEnvVarProcessor::toOrigin((string) ($_SERVER[$envName] ?? $_ENV[$envName] ?? getenv($envName)));
        self::assertNotSame('', $origin, sprintf('%s is a valid URL', $envName));

        return $origin;
    }
}
