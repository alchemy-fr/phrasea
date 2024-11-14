<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\CoreBundle\Util\UrlUtil;
use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TransformationContext implements TransformationContextInterface
{
    private BuildHashes $buildHashes;

    public function __construct(
        private string $workingDirectory,
        private string $cacheDir,
        private MimeTypeGuesser $mimeTypeGuesser,
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private ?MetadataContainerInterface $metadata = null,
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

    public function guessMimeTypeFromPath(string $path): ?string
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
        $mimeType = $this->guessMimeTypeFromPath(UrlUtil::getUriWithoutQuery($uri));
        $extension = null !== $mimeType ? $this->getExtension($mimeType) : null;

        $path = $cacheDir.'/'.md5($uri).($extension ? '.'.$extension : '');
        if (!file_exists($path)) {
            $contentType = $this->download($uri, $path);
            if (null === $mimeType && null !== $contentType) {
                $newPath = $path.'.'.$this->getExtension($contentType);
                rename($path, $newPath);
                $path = $newPath;
            }
        }

        return $path;
    }

    private function download(string $uri, string $dest): ?string
    {
        $response = $this->client->request('GET', $uri);

        $fileHandler = fopen($dest, 'w');
        foreach ($this->client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }
        fclose($fileHandler);

        $contentType = $response->getHeaders()['content-type'] ?? null;
        if (empty($contentType)) {
            return null;
        }

        return is_array($contentType) ? $contentType[0] : $contentType;
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
}
