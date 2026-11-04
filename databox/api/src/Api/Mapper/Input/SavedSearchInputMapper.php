<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\SavedSearchInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\SavedSearch\SavedSearch;
use App\Model\SavedSearchPrivacyEnum;
use App\Security\Voter\SavedSearchVoter;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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

        $published = false;
        if (null !== $data->privacy) {
            $privacy = SavedSearchPrivacyEnum::from($data->privacy);
            // Keeping the current privacy needs no capability (e.g. renaming a public search)
            $published = SavedSearchPrivacyEnum::Secret !== $privacy && $privacy !== $object->getPrivacy();
            $object->setPrivacy($privacy);
        }

        if (null !== $data->name) {
            $object->setName($data->name);
        }

        if (null !== $data->data) {
            $object->setData($data->data);
        }

        $this->processOwnerId($object);

        if ($published && !$this->isGranted(SavedSearchVoter::PUBLISH, $object)) {
            throw new AccessDeniedHttpException('You are not allowed to publish this saved search.');
        }

        return $object;
    }
}
