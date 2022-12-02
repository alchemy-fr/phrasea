<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use GuzzleHttp\Client;

class RemoveBgClient
{
    private FileFetcher $fileFetcher;
    private string $cacheDir;

    public function __construct(FileFetcher $fileFetcher, string $cacheDir)
    {
        $this->fileFetcher = $fileFetcher;
        $this->cacheDir = $cacheDir;
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

    public function getBgRemoved(File $file, string $apiKey): string
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $path = $this->fileFetcher->getFile($file);

        $md5 = md5_file($path);

        $cacheFile = sprintf('%s/%s', $this->cacheDir, $md5);
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        $client = $this->createClient($apiKey);

        $res = $client->post('/v1.0/removebg', [
            'multipart' => [
                [
                    'name' => 'image_file',
                    'contents' => fopen($path, 'r'),
                ],
                [
                    'name' => 'size',
                    'contents' => 'auto',
                ],
            ],
        ]);

        file_put_contents($cacheFile, $res->getBody());

        return $cacheFile;
    }
}
