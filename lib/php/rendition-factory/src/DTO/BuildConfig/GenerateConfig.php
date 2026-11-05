<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

/**
 * Builds a rendition without any source file: the first enabled transformation
 * is a generator module, fed with the input files given by the caller.
 */
final readonly class GenerateConfig
{
    public function __construct(
        /** @var Transformation[] */
        private array $transformations,

        private array $normalization,

        /**
         * What the caller feeds the generator with. Not interpreted by the library.
         */
        private ?string $input = null,
    ) {
    }

    public function getTransformations(): array
    {
        return $this->transformations;
    }

    /**
     * @return Transformation[] indexed from 0
     */
    public function getEnabledTransformations(): array
    {
        return array_values(array_filter($this->transformations, function (Transformation $transformation) {
            return $transformation->isEnabled();
        }));
    }

    public function getNormalization(): array
    {
        return $this->normalization;
    }

    public function getInput(): ?string
    {
        return $this->input;
    }
}
