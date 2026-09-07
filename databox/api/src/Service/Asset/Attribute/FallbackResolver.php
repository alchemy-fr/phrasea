<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Service\Asset\Attribute\Index\AttributeIndex;

readonly class FallbackResolver
{
    public function __construct(
        private AttributeValueResolver $attributeValueResolver,
    ) {
    }

    public function resolveAttrFallback(
        Asset $asset,
        string $locale,
        AttributeDefinition $definition,
        AttributeIndex $attributesIndex,
        array $parentDefinitions = [],
    ): array {
        return $this->attributeValueResolver->resolveAttrValues(
            fn (AttributeDefinition $definition) => $definition->getFallback(),
            Attribute::ORIGIN_FALLBACK,
            $asset,
            $locale,
            $definition,
            $attributesIndex,
            $parentDefinitions
        );
    }
}
