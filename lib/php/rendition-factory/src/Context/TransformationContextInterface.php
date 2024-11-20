<?php

namespace Alchemy\RenditionFactory\Context;

interface TransformationContextInterface
{
    public function createTmpFilePath(?string $extension): string;

    public function getCacheDir(string $folder): string;

    public function guessMimeTypeFromPath(string $path): ?string;

    public function getExtension(string $mimeType): ?string;

    public function getRemoteFile(string $uri): string;

    public function getMetadata(string $name): ?string;

    public function getTemplatingContext(): array;

    public function getWorkingDirectory(): string;

    public function log(string $message, array $context = []): void;

    public function getBuildHashes(): BuildHashes;
}
