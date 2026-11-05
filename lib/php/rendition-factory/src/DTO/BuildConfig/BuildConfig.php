<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

final readonly class BuildConfig
{
    /**
     * Key of the "generate" section, also the family level of its build hashes.
     */
    final public const string GENERATE = 'generate';

    public function __construct(
        private array $families,
        private ?GenerateConfig $generate,
    ) {
    }

    public function getFamily(FamilyEnum $family): ?FamilyBuildConfig
    {
        return $this->families[$family->value] ?? null;
    }

    public function getGenerate(): ?GenerateConfig
    {
        return $this->generate;
    }
}
