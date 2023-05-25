<?php

declare(strict_types=1);

namespace App\Border;

use GuzzleHttp\Client;

class UriDownloader
{
    public function __construct(private readonly Client $client)
    {
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
