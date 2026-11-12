<?php

declare(strict_types=1);

namespace App\Border;

use App\Border\Exception\UnsupportedUriException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class UriDownloader
{
    private const array SUPPORTED_SCHEMES = ['http', 'https'];

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnv,
    ) {
    }

    /**
     * @param bool $allowPrivateNetwork only for URLs the platform trusts: user-supplied ones
     *                                  could reach the internal services or the cloud metadata
     *
     * @return string The temporary file path
     *
     * @throws UnsupportedUriException when $uri is not an HTTP(S) URL
     */
    public function download(string $uri, array &$headers = [], ?string $path = null, bool $allowPrivateNetwork = false): string
    {
        $client = $this->createClient($uri, $allowPrivateNetwork);
        $response = $client->request('GET', $uri);

        $path ??= sys_get_temp_dir().'/'.uniqid('download-file');
        $fileHandler = fopen($path, 'w');
        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

        $headers = $response->getHeaders();

        return $path;
    }

    /**
     * Downloads the first $length bytes of the resource, at most.
     *
     * @throws UnsupportedUriException when $uri is not an HTTP(S) URL
     */
    public function downloadHead(string $uri, int $length, bool $allowPrivateNetwork = false): string
    {
        $client = $this->createClient($uri, $allowPrivateNetwork);
        // Servers ignoring the range send the whole content: the transfer is cut once the head is received.
        $response = $client->request('GET', $uri, [
            'headers' => [
                'Range' => sprintf('bytes=0-%d', $length - 1),
            ],
        ]);

        $head = '';
        foreach ($client->stream($response) as $chunk) {
            $head .= $chunk->getContent();
            if (strlen($head) >= $length) {
                $response->cancel();
                break;
            }
        }

        return substr($head, 0, $length);
    }

    private function createClient(string $uri, bool $allowPrivateNetwork): HttpClientInterface
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::SUPPORTED_SCHEMES, true)) {
            throw new UnsupportedUriException($uri);
        }

        return $allowPrivateNetwork || 'dev' === $this->kernelEnv ? $this->client : new NoPrivateNetworkHttpClient($this->client);
    }
}
