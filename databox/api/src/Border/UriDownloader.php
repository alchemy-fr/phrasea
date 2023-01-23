<?php

declare(strict_types=1);

namespace App\Border;

use GuzzleHttp\Client;

class UriDownloader
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return string The temporary file path
     */
    public function download(string $uri, array &$headers = []): string
    {
        $tmpFile = sys_get_temp_dir().'/'.uniqid('incoming-file');
        $res = $this->client->get($uri, [
            'sink' => $tmpFile,
        ]);

        $headers = $res->getHeaders();

        return $tmpFile;
    }
}
