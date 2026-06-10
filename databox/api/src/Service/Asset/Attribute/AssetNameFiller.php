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
    ): void {
        $target = $asset->isStory() ? AssetTypeEnum::Story : AssetTypeEnum::Asset;
        $nameAttributes = $this->attributeDefinitionRepository->getWorkspaceFillFromNameDefinitions($asset->getWorkspaceId());

        foreach ($nameAttributes as $nameAttribute) {
            if (!$nameAttribute->isForTarget($target)) {
                continue;
            }

            $input = new AttributeInput();
            $input->definition = $nameAttribute;
            $input->asset = $asset;
            $input->value = $name;

            $this->attributeValidator->validateAttribute($nameAttribute, $input, 'name');

            $this->attributeAssigner->upsertAttribute($nameAttribute, $asset, $input);
        }
    }
}
