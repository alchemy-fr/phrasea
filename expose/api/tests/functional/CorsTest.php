<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\CoreBundle\Env\OriginEnvVarProcessor;
use Symfony\Component\HttpFoundation\Response;

class CorsTest extends AbstractExposeTestCase
{
    private const string OTHER_ORIGIN = 'https://attacker.example';

    public function testApiIsOpenToTheClientWithoutCredentials(): void
    {
        $response = $this->preflight('/publications', 'POST', self::clientOrigin());
        $this->assertSame(self::clientOrigin(), $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsStringIgnoringCase('x-passwords', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsStringIgnoringCase('x-share-tokens', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));

        $response = $this->request(null, 'GET', '/publications', null, [], ['HTTP_ORIGIN' => self::clientOrigin()]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::clientOrigin(), $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function testApiIsClosedToOtherOrigins(): void
    {
        $response = $this->preflight('/publications', 'POST', self::OTHER_ORIGIN);
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $response = $this->request(null, 'GET', '/publications', null, [], ['HTTP_ORIGIN' => self::OTHER_ORIGIN]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testAdminSendsNoCorsHeader(): void
    {
        foreach (['/admin', '/admin/login', '/admin/publication'] as $uri) {
            $this->assertSame([], $this->getCorsHeaders($this->preflight($uri, 'GET', self::clientOrigin())), $uri);
        }
    }

    public function testWebVttSendsNoCredentials(): void
    {
        $response = $this->request(null, 'OPTIONS', '/publications/foo/vtt/bar.vtt');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    private function preflight(string $uri, string $method, string $origin): Response
    {
        return $this->request(null, 'OPTIONS', $uri, null, [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization, x-passwords, x-share-tokens',
        ]);
    }

    /**
     * @return array<string, list<string|null>>
     */
    private function getCorsHeaders(Response $response): array
    {
        return array_filter(
            $response->headers->all(),
            fn (string $name): bool => str_starts_with($name, 'access-control-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private static function clientOrigin(): string
    {
        return self::configuredOrigin('EXPOSE_CLIENT_URL');
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
