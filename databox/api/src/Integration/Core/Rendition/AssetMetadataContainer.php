<?php

namespace App\Integration\Core\Rendition;

use Alchemy\RenditionFactory\DTO\Metadata\MetadataContainerInterface;
use App\Asset\Attribute\AssetTitleResolver;
use App\Asset\Attribute\AttributesResolver;
use App\Asset\Attribute\Index\AttributeIndex;
use App\Entity\Core\Asset;

final class AssetMetadataContainer implements MetadataContainerInterface
{
    private ?array $attributes = null;
    private ?AttributeIndex $attributeIndex = null;

    public function __construct(
        private readonly Asset $asset,
        private readonly AttributesResolver $attributesResolver,
        private readonly AssetTitleResolver $assetTitleResolver,
    ) {
    }

    public function getMetadata(string $name): ?string
    {
        $prefix = 'attr.';
        if (str_starts_with($name, $prefix)) {
            return $this->getAttribute(substr($name, strlen($prefix)));
        }

        switch ($name) {
            case 'title':
                $this->fetchAttributes();

                return $this->assetTitleResolver->resolveTitle($this->asset, $this->attributeIndex, ['en']);
            default:
                return null;
        }
    }

    public function getAttribute(string $name): mixed
    {
        $this->fetchAttributes();

        return $this->attributes[$name] ?? null;
    }

    private function fetchAttributes(): void
    {
        if (null === $this->attributeIndex) {
            $this->attributeIndex = $this->attributesResolver->resolveAssetAttributes($this->asset, false);

            foreach ($this->attributeIndex->getFlattenAttributes() as $attribute) {
                $this->attributes[$attribute->getDefinition()->getSlug()] = $attribute->getValue();
            }
        }
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
