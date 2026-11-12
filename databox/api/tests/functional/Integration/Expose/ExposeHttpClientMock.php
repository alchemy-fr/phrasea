<?php

declare(strict_types=1);

namespace App\Tests\Functional\Integration\Expose;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The HTTP client of the Expose integrations in the test environment: the requests to
 * the FakeExposeApi set by the test are served by it, the other ones go through.
 */
final class ExposeHttpClientMock implements HttpClientInterface
{
    use DecoratorTrait {
        withOptions as private decoratedWithOptions;
    }

    public static ?FakeExposeApi $api = null;

    private ?string $baseUri = null;

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $absoluteUrl = str_contains($url, '://') ? $url : rtrim((string) $this->baseUri, '/').$url;
        if (null !== self::$api && str_starts_with($absoluteUrl, FakeExposeApi::BASE_URL)) {
            return new MockHttpClient(self::$api)->request($method, $absoluteUrl, $options);
        }

        return $this->client->request($method, $url, $options);
    }

    public function withOptions(array $options): static
    {
        $clone = $this->decoratedWithOptions($options);
        if (isset($options['base_uri'])) {
            $clone->baseUri = $options['base_uri'];
        }

        return $clone;
    }
}
