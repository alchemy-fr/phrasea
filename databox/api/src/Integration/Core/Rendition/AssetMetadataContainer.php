<?php

namespace App\Integration\Core\Rendition;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use App\Asset\Attribute\AttributesResolver;
use App\Entity\Core\Asset;

final class AssetMetadataContainer implements MetadataContainerInterface
{
    private ?array $attributes = null;

    public function __construct(
        private Asset $asset,
        private AttributesResolver $attributesResolver,
    )
    {
    }

    public function getMetadata(string $name): string|null
    {
        if (null === $this->attributes) {
            $attributeIndex = $this->attributesResolver->resolveAssetAttributes($this->asset, false);

            $this->attributes = [];

            foreach ($attributeIndex->getFlattenAttributes() as $attribute) {
                $this->attributes[$attribute->getDefinition()->getName()] = $attribute->getValue();
            }
        }

        return $this->attributes[$name] ?? null;
    }
}
