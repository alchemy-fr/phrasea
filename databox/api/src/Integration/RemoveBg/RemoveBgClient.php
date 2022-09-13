<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

class RemoveBgClient
{
    private FileFetcher $fileFetcher;

    public function __construct(FileFetcher $fileFetcher)
    {
        $this->fileFetcher = $fileFetcher;
    }

    private function createClient(string $apiKey): Client
    {
        return new Client([
            'base_uri' => 'https://api.remove.bg',
            'headers' => [
                'X-Api-Key' => $apiKey,
            ],
        ]);
    }

    public function getBgRemoved(File $file, string $apiKey): StreamInterface
    {
        $client = $this->createClient($apiKey);

        $path = $this->fileFetcher->getFile($file);

        $res = $client->post('/v1.0/removebg', [
            'multipart' => [
                [
                    'name'     => 'image_file',
                    'contents' => fopen($path, 'r')
                ],
                [
                    'name'     => 'size',
                    'contents' => 'auto'
                ],
            ],
        ]);

        return $res->getBody();
    }
}
