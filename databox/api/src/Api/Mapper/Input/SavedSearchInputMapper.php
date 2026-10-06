<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\SavedSearchInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: SavedSearchInput::class)]
class SavedSearchInputMapper extends AbstractFileInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param SavedSearchInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        /** @var SavedSearch $object */
        $object = $target ?? new SavedSearch();

        if (null !== $data->privacy) {
            $object->setPrivacy(SavedSearchPrivacyEnum::from($data->privacy));
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }

        if (null !== $data->data) {
            $object->setData($data->data);
        }

        return $this->processOwnerId($object);
    }
}
