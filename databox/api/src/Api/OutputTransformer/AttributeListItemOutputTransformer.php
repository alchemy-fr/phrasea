<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\AttributeListItemOutput;
use App\Entity\AttributeList\AttributeListItem;

class AttributeListItemOutputTransformer implements OutputTransformerInterface
{
    public function supports(string $outputClass, object $data): bool
    {
        return AttributeListItemOutput::class === $outputClass && $data instanceof AttributeListItem;
    }

    /**
     * @param AttributeListItem $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        return $this->createOutput($data);
    }

    public function createOutput(AttributeListItem $data): AttributeListItemOutput
    {
        return new AttributeListItemOutput(
            id: $data->getId(),
            definition: $data->getDefinition()?->getId(),
            key: $data->getKey(),
            type: $data->getType(),
            displayEmpty: $data->isDisplayEmpty(),
            format: $data->getFormat(),
        );
    }
}
