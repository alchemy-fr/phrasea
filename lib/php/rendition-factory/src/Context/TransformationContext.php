<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Alchemy\RenditionFactory\Templating\TemplateResolverInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment as TwigEnvironment;

final readonly class TransformationContext implements TransformationContextInterface
{
    private BuildHashes $buildHashes;

    public function __construct(
        private string $workingDirectory,
        private string $cacheDir,
        private MimeTypeGuesser $mimeTypeGuesser,
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private TwigEnvironment $twig,
        private TemplateResolverInterface $templateResolver,
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

    public function getTwig(): TwigEnvironment
    {
        return $this->twig;
    }
    public function getTemplateResolver(): TemplateResolverInterface
    {
        return $this->templateResolver;
    }
}
