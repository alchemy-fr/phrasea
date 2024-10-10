<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

final readonly class FamilyBuildConfig
{
    public function __construct(
        /** @var Transformation[] */
        private array $transformations,

        private array $normalization,
    ) {
    }

    public function getTransformations(): array
    {
        return array_filter(
            $this->transformations,
            fn (Transformation $transformation) => $transformation->isEnabled()
        );
    }

    public function getNormalization(): array
    {
        return $this->normalization;
    }
}
