<?php

namespace Alchemy\RenditionFactory\DTO\Metadata;

interface MetadataContainerInterface
{
    public function getMetadata(string $name): string|null;

    public function getTemplatingContext(): array;
}
