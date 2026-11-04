<?php

namespace Alchemy\RenditionFactory\DTO;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;

final readonly class CreateRenditionOptions
{
    public function __construct(
        private ?string $workingDirectory = null,
        private ?string $cacheDirectory = null,
        private ?MetadataContainerInterface $metadataContainer = null,
        /**
         * The files fed to a generator module (see BuildConfig::getGenerate()).
         *
         * @var InputFileInterface[]
         */
        private array $inputFiles = [],
    ) {
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function getCacheDirectory(): ?string
    {
        return $this->cacheDirectory;
    }

    public function getMetadataContainer(): ?MetadataContainerInterface
    {
        return $this->metadataContainer;
    }

    /**
     * @return InputFileInterface[]
     */
    public function getInputFiles(): array
    {
        return $this->inputFiles;
    }
}
