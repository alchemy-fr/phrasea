<?php

namespace App\Integration\Core\Rendition;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use App\Asset\Attribute\AttributesResolver;
use App\Entity\Core\Asset;

final class AssetMetadataContainer implements MetadataContainerInterface
{
    private ?array $attributes = null;

    public function __construct(
        private readonly Asset $asset,
        private readonly AttributesResolver $attributesResolver,
    )
    {
    }

    public function getMetadata(string $name): string|null
    {
        $prefix = 'attr.';
        if (str_starts_with($name, $prefix)) {
            return $this->getAttribute(substr($name, strlen($prefix)));
        }

        return match ($name) {
            'title' => $this->asset->getTitle(),
            default => null,
        };
    }

    public function getAttribute(string $name): mixed
    {
        if (null === $this->attributes) {
            $attributeIndex = $this->attributesResolver->resolveAssetAttributes($this->asset, false);

            foreach ($attributeIndex->getFlattenAttributes() as $attribute) {
                $this->attributes[$attribute->getDefinition()->getSlug()] = $attribute->getValue();
            }
        }

        return $this->attributes[$name] ?? null;
    }

    public function getTemplatingContext(): array
    {
        return [
            'asset' => $this->asset,
            'file' => $this->asset->getSource(),
            'attr' => new AssetAttributeAccessor($this),
        ];
    }
}
