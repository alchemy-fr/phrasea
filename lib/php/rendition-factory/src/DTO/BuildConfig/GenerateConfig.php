<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

final readonly class GenerateConfig
{
    public function __construct(
        /** @var Transformation[] */
        private array $transformations,

        private array $normalization,
    ) {
    }

    public function getTransformations(): array
    {
        return $this->transformations;
    }

    public function getEnabledTransformations(): array
    {
        return array_filter($this->transformations, function (Transformation $transformation) {
            return $transformation->isEnabled();
        });
    }

    public function getNormalization(): array
    {
        return $this->normalization;
    }
}
