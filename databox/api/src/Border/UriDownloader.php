<?php

declare(strict_types=1);

namespace App\Border;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Alchemy\CoreBundle\Util\HttpDownloadUtil;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\Exception\UnsupportedUriException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

readonly class UriDownloader
{
    private const array SUPPORTED_SCHEMES = ['http', 'https'];

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire(param: 'kernel.environment')]
        private string $kernelEnv,
        private MultipartUploadPlanner $uploadPlanner,
        /**
         * In seconds, for the whole transfer: a slow server must not hold a worker.
         */
        #[Autowire(param: 'app.download.max_duration')]
        private float $maxDuration,
    ) {
    }

    /**
     * @param bool $allowPrivateNetwork only for URLs the platform trusts: user-supplied ones
     *                                  could reach the internal services or the cloud metadata
     *
     * @return string The temporary file path
     *
     * @throws UnsupportedUriException   when $uri is not an HTTP(S) URL
     * @throws DownloadTooLargeException when the content would not fit in the storage
     */
    public function download(string $uri, array &$headers = [], ?string $path = null, bool $allowPrivateNetwork = false): string
    {
        $client = $this->createClient($uri, $allowPrivateNetwork);
        $response = $client->request('GET', $uri, [
            'max_duration' => $this->maxDuration,
        ]);

        $path ??= sys_get_temp_dir().'/'.uniqid('download-file');
        HttpDownloadUtil::streamToFile($client, $response, $path, $this->uploadPlanner->getMaxObjectSize());

        $headers = $response->getHeaders();

        return $path;
    }

    /**
     * Downloads several resources at once, at most $concurrency in flight: a failure (an unsupported
     * URI included) only stops its own download, whose partial file is removed.
     *
     * @param array<string, string> $uris                URI keyed by destination path
     * @param bool                  $allowPrivateNetwork see download()
     *
     * @return array<string, \Throwable> the failures, keyed by destination path
     */
    public function downloadMany(array $uris, int $concurrency, bool $allowPrivateNetwork = false): array
    {
        $failures = [];
        $requests = [];
        $client = $this->getClient($allowPrivateNetwork);
        foreach ($uris as $path => $uri) {
            try {
                self::assertSupportedUri($uri);
            } catch (UnsupportedUriException $e) {
                $failures[$path] = $e;
                continue;
            }

            $requests[$path] = fn (): ResponseInterface => $client->request('GET', $uri, [
                'max_duration' => $this->maxDuration,
            ]);
        }

        return $failures + HttpDownloadUtil::streamAllToFiles($client, $requests, $concurrency, $this->uploadPlanner->getMaxObjectSize());
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
            'max_duration' => $this->maxDuration,
        ]);
        if (416 === $response->getStatusCode()) {
            // A range starting at 0 is only refused for an empty resource, or by a server mishandling ranges
            $response->cancel();
            $response = $client->request('GET', $uri, [
                'max_duration' => $this->maxDuration,
            ]);
        }
        // Reading the status code consumed the first chunk, whose arrival is where stream() checks the status:
        // without this check, an error page would pass for the head of the content.
        $response->getHeaders();

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
        self::assertSupportedUri($uri);

        return $this->getClient($allowPrivateNetwork);
    }

    private function getClient(bool $allowPrivateNetwork): HttpClientInterface
    {
        return $allowPrivateNetwork || 'dev' === $this->kernelEnv ? $this->client : new NoPrivateNetworkHttpClient($this->client);
    }

    /**
     * @throws UnsupportedUriException
     */
    private static function assertSupportedUri(string $uri): void
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), self::SUPPORTED_SCHEMES, true)) {
            throw new UnsupportedUriException($uri);
        }
    }
}
