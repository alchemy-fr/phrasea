<?php

declare(strict_types=1);

namespace App\Border;

use App\Border\Exception\UnsupportedUriException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class UriDownloader
{
    private const array SUPPORTED_SCHEMES = ['http', 'https'];

    public function __construct(private HttpClientInterface $client)
    {
    }

    /**
     * @return string The temporary file path
     *
     * @throws UnsupportedUriException when $uri is not an HTTP(S) URL
     */
    public function download(string $uri, array &$headers = [], ?string $path = null): string
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::SUPPORTED_SCHEMES, true)) {
            throw new UnsupportedUriException($uri);
        }

        $response = $this->client->request('GET', $uri);

        $path ??= sys_get_temp_dir().'/'.uniqid('download-file');
        $fileHandler = fopen($path, 'w');
        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

        $headers = $response->getHeaders();

        return $path;
    }
}
