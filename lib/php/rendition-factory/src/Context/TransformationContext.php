<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TransformationContext implements TransformationContextInterface
{
    private BuildHashes $buildHashes;
    private bool $isProjection = true;

    public function __construct(
        private readonly string $workingDirectory,
        private readonly string $cacheDir,
        private readonly MimeTypeGuesser $mimeTypeGuesser,
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly ?MetadataContainerInterface $metadata = null,
    ) {
        $this->buildHashes = new BuildHashes();
    }

    public function createTmpFilePath(?string $extension): string
    {
        $path = uniqid($this->workingDirectory.'/');
        if (!empty($extension)) {
            $path .= '.'.$extension;
        }

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

    public function getMetadata(string $name): ?string
    {
        return $this->metadata?->getMetadata($name);
    }

    public function getTemplatingContext(): array
    {
        return $this->metadata?->getTemplatingContext() ?? [];
    }

    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    public function log(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getBuildHashes(): BuildHashes
    {
        return $this->buildHashes;
    }

    public function setNotProjection(): void
    {
        $this->isProjection = false;
    }

    public function isProjection(): bool
    {
        return $this->isProjection;
    }
}
