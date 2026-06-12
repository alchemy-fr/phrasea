<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Http\LocaleContext;
use App\Model\AssetTypeEnum;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Service\Asset\Attribute\Index\AttributeIndex;
use Psr\Log\LoggerInterface;

final readonly class AssetNameResolver
{
    public function __construct(
        private LoggerInterface $logger,
        private AttributesResolver $attributesResolver,
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private LocaleContext $localeContext,
        private AttributeTypeRegistry $attributeTypeRegistry,
    ) {
    }

    public function resolveNameAsString(
        Asset $asset,
        ?AttributeIndex $attributesIndex = null,
        ?array $preferredLocales = null,
    ): ?string {
        $name = $this->resolveName($asset, $attributesIndex, $preferredLocales);
        if ($name instanceof Attribute) {
            $attributeType = $this->attributeTypeRegistry->getStrictType($name->getDefinition()->getType());

            return $attributeType->getStringValue($name->getValue(), null);
        }

        return $name;
    }

    public function resolveName(
        Asset $asset,
        ?AttributeIndex $attributesIndex = null,
        ?array $preferredLocales = null,
    ): Attribute|string|null {
        $attributesIndex ??= $asset->attributesIndex ?? $this->attributesResolver->resolveAssetAttributes($asset, true);

        $target = $asset->isStory() ? AssetTypeEnum::Story : AssetTypeEnum::Asset;
        $nameAttributes = $this->attributeDefinitionRepository->getWorkspaceUseAsNameDefinitions($asset->getWorkspaceId());

        $preferredLocales ??= $this->localeContext->getPreferredLocales($asset->getWorkspace());

        foreach ($nameAttributes as $nameAttribute) {
            if (!$nameAttribute->isForTarget($target)) {
                continue;
            }

            if ($nameAttribute->isMultiple()) {
                $this->logger->warning(sprintf('Cannot use multiple attribute definition "%s" as name', $nameAttribute->getId()));
                continue;
            }
            $definitionId = $nameAttribute->getId();

            foreach ($preferredLocales as $l) {
                if (null !== $attribute = $attributesIndex->getAttribute($definitionId, $l)) {
                    return $attribute;
                }
            }
        }

        return $asset->getSource()?->getOriginalName();
    }
}
