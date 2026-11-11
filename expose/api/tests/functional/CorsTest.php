<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

class CorsTest extends AbstractExposeTestCase
{
    private const string ORIGIN = 'https://elsewhere.example';

    public function testApiIsOpenWithoutCredentials(): void
    {
        $response = $this->preflight('/publications', 'POST');
        $this->assertSame(self::ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsStringIgnoringCase('x-passwords', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsStringIgnoringCase('x-share-tokens', (string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));

        $response = $this->request(null, 'GET', '/publications', null, [], ['HTTP_ORIGIN' => self::ORIGIN]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function testAdminSendsNoCorsHeader(): void
    {
        foreach (['/admin', '/admin/login', '/admin/publication'] as $uri) {
            $this->assertSame([], $this->getCorsHeaders($this->preflight($uri, 'GET')), $uri);
        }
    }

    public function testWebVttSendsNoCredentials(): void
    {
        $response = $this->request(null, 'OPTIONS', '/publications/foo/vtt/bar.vtt');
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    private function preflight(string $uri, string $method): Response
    {
        return $this->request(null, 'OPTIONS', $uri, null, [], [
            'HTTP_ORIGIN' => self::ORIGIN,
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
}
