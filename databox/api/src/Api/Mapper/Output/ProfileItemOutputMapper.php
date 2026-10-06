<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\ProfileItemOutput;
use App\Entity\Profile\ProfileItem;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: ProfileItemOutput::class)]
class ProfileItemOutputMapper implements OutputMapperInterface
{
    public function supports(object $data): bool
    {
        return $data instanceof ProfileItem;
    }

    /**
     * @param ProfileItem $data
     */
    public function map(object $data, array $context = []): object
    {
        return $this->createOutput($data);
    }

    public function createOutput(ProfileItem $data): ProfileItemOutput
    {
        return new ProfileItemOutput(
            id: $data->getId(),
            definition: $data->getDefinition()?->getId(),
            key: $data->getKey(),
            section: $data->getSection(),
            type: $data->getType(),
            displayEmpty: $data->isDisplayEmpty(),
            format: $data->getFormat(),
            placement: $data->getPlacement(),
            variant: $data->getVariant(),
            color: $data->getColor(),
            size: $data->getSize(),
            showLabel: $data->isShowLabel(),
            showIcon: $data->isShowIcon(),
        );
    }
}
