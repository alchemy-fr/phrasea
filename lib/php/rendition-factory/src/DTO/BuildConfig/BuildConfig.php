<?php

namespace Alchemy\RenditionFactory\DTO\BuildConfig;

use Alchemy\RenditionFactory\DTO\FamilyEnum;

final readonly class BuildConfig
{
    public function __construct(
        private array $families,
        private array $generate,
    ) {
    }

    public function getFamily(FamilyEnum $family): ?FamilyBuildConfig
    {
        return $this->families[$family->value] ?? null;
    }

    public function getGenerate(): array
    {
        return $this->generate;
    }
}
