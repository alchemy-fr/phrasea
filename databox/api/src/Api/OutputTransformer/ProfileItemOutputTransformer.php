<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\ProfileItemOutput;
use App\Entity\Profile\ProfileItem;

class ProfileItemOutputTransformer implements OutputTransformerInterface
{
    public function supports(string $outputClass, object $data): bool
    {
        return ProfileItemOutput::class === $outputClass && $data instanceof ProfileItem;
    }

    /**
     * @param ProfileItem $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        return $this->createOutput($data);
    }

    public function createOutput(ProfileItem $data): ProfileItemOutput
    {
        return new ProfileItemOutput(
            id: $data->getId(),
            definition: $data->getDefinition()?->getId(),
            key: $data->getKey(),
            type: $data->getType(),
            displayEmpty: $data->isDisplayEmpty(),
            format: $data->getFormat(),
        );
    }
}
