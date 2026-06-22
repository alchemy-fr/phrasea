<?php

declare(strict_types=1);

namespace App\Service\Asset\Attribute;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Attribute\AttributeValidator;
use App\Entity\Core\Asset;
use App\Model\AssetTypeEnum;
use App\Repository\Core\AttributeDefinitionRepository;

final readonly class AssetNameFiller
{
    public function __construct(
        private AttributeDefinitionRepository $attributeDefinitionRepository,
        private AttributeAssigner $attributeAssigner,
        private AttributeValidator $attributeValidator,
    ) {
    }

    public function fillName(
        Asset $asset,
        ?string $name,
        bool $persist = true,
    ): array {
        $target = $asset->isStory() ? AssetTypeEnum::Story : AssetTypeEnum::Asset;

        if (null === $asset->getWorkspace()) {
            throw new \InvalidArgumentException('Asset must have a workspace to fill name');
        }

        $nameAttributes = $this->attributeDefinitionRepository->getWorkspaceFillFromNameDefinitions($asset->getWorkspaceId());

        $filledNameAttributes = [];
        foreach ($asset->getAttributes() as $attribute) {
            $definition = $attribute->getDefinition();
            if ($definition->isFillFromName()) {
                $filledNameAttributes[$definition->getId()] = true;
            }
        }

        $attributes = [];
        foreach ($nameAttributes as $nameAttribute) {
            if (isset($filledNameAttributes[$nameAttribute->getId()])) {
                continue;
            }

            if (!$nameAttribute->isForTarget($target)) {
                continue;
            }

            $input = new AttributeInput();
            $input->definition = $nameAttribute;
            $input->asset = $asset;
            $input->value = $name;

            $this->attributeValidator->validateAttribute($nameAttribute, $input, 'name');

            $a = $this->attributeAssigner->upsertOrRemoveAttribute($nameAttribute, $asset, $input, $persist);
            if (null !== $a) {
                $attributes[] = $a;
            }
        }

        return $attributes;
    }
}
