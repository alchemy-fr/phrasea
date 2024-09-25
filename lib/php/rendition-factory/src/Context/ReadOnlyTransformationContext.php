<?php

namespace Alchemy\RenditionFactory\Context;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use Psr\Log\LoggerInterface;

final readonly class ReadOnlyTransformationContext implements TransformationContextInterface
{
    public function __construct(
        private MimeTypeGuesser $mimeTypeGuesser,
        private LoggerInterface $logger,
        private ?MetadataContainerInterface $metadata = null,
    ) {
    }

    public function createTmpFilePath(?string $extension): string
    {
        throw new \InvalidArgumentException('Cannot create temporary file in read-only context');
    }

    public function getCacheDir(string $folder): string
    {
        throw new \InvalidArgumentException('Cannot get cache directory in read-only context');
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
        throw new \InvalidArgumentException('Cannot get remote file in read-only context');
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
        throw new \InvalidArgumentException('Cannot get working directory in read-only context');
    }

    public function log(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function getBuildHashes(): BuildHashes
    {
        throw new \InvalidArgumentException('Cannot get BuildHashes in read-only context');
    }
}
