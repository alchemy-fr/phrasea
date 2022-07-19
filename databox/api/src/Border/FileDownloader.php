<?php

declare(strict_types=1);

namespace App\Border;

use GuzzleHttp\Client;

class FileDownloader
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

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
