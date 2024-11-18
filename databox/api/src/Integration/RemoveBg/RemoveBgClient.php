<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class RemoveBgClient
{
    public function __construct(
        private FileFetcher $fileFetcher,
        private string $cacheDir,
        private HttpClientInterface $removeBgClient,
    ) {
    }

    public function getBgRemoved(File $file, string $apiKey): string
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $path = $this->fileFetcher->getFile($file);
        $md5 = md5_file($path).'-v2';

        $cacheFile = sprintf('%s/%s', $this->cacheDir, $md5);
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        $res = $this->removeBgClient->request('POST', '/v1.0/removebg', [
            'headers' => [
                'X-Api-Key' => $apiKey,
            ],
            'body' => [
                'image_file' => fopen($path, 'r'),
                'size' => 'auto',
            ],
        ]);

        try {
            $fileHandler = fopen($cacheFile, 'w');
            foreach ($this->removeBgClient->stream($res) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);
        } catch (\Throwable $e) {
            fclose($fileHandler);
            @unlink($cacheFile);
            throw $e;
        }

        return $cacheFile;
    }
}
