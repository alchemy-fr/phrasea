<?php

declare(strict_types=1);

namespace App\Border;

use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class UriDownloader
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    /**
     * @return string The temporary file path
     */
    public function download(string $uri, array &$headers = []): string
    {
        $response = $this->client->request('GET', $uri);

        $tmpFile = sys_get_temp_dir().'/'.uniqid('incoming-file');
        $fileHandler = fopen($tmpFile, 'w');
        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

        $headers = $response->getHeaders();

        return $tmpFile;
    }
}
