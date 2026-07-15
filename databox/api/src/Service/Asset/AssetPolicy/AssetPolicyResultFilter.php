<?php

namespace App\Service\Asset\AssetPolicy;

final class AssetPolicyResultFilter
{
    public function __construct(
        private array $filteredRenditions = [],
        private array $filteredAttributes = [],
    ) {
    }

    public function getFilteredRenditions(): array
    {
        return $this->filteredRenditions;
    }

    public function getFilteredAttributes(): array
    {
        return $this->filteredAttributes;
    }

    public function addFilteredRendition(string $id): void
    {
        $this->filteredRenditions[] = $id;

    }

    public function addFilteredAttribute(string $id): void
    {
        $this->filteredAttributes[] = $id;
    }
}
