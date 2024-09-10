<?php

namespace Alchemy\RenditionFactory\Transformer;

use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TransformationContext
{
    public function __construct(
        private string $workingDirectory,
        private string $cacheDir,
        private MimeTypeGuesser $mimeTypeGuesser,
        private HttpClientInterface $client,
    ) {
    }

    public function createTmpFilePath(string $extension): string
    {
        $path = uniqid($this->workingDirectory.'/').'.'.$extension;

        if (file_exists($path)) {
            return $this->createTmpFilePath($extension);
        }

        return $path;
    }

    public function getCacheDir(string $folder): string
    {
        $cacheDir = $this->cacheDir.'/'.$folder;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, recursive: true);
        }

        return $cacheDir;
    }

    public function guessMimeTypeFromPath(string $path): string
    {
        return $this->mimeTypeGuesser->guessMimeTypeFromPath($path);
    }

    public function getExtension(string $mimeType): ?string
    {
        return $this->mimeTypeGuesser->getExtension($mimeType);
    }

    public function getFormat(string $mimeType): ?string
    {
        return $this->mimeTypeGuesser->getFormat($mimeType);
    }

    public function getRemoteFile(string $uri): string
    {
        $cacheDir = $this->getCacheDir('remote');
        $mimeType = $this->guessMimeTypeFromPath($uri);
        $extension = $this->getExtension($mimeType);

        $path = $cacheDir.'/'.md5($uri).($extension ? '.'.$extension : '');
        if (!file_exists($path)) {
            $this->download($uri, $path);
        }

        return $path;
    }

    private function download(string $uri, string $dest): void
    {
        $response = $this->client->request('GET', $uri);

        $fileHandler = fopen($dest, 'w');
        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);
    }
}
