<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\SavedSearchInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\SavedSearch\SavedSearch;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class SavedSearchInputTransformer extends AbstractFileInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return SavedSearch::class === $resourceClass && $data instanceof SavedSearchInput;
    }

    /**
     * @param SavedSearchInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var SavedSearch $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new SavedSearch();

        if (null !== $data->public) {
            $object->setPublic($data->public);
        }

        if (null !== $data->title) {
            $object->setTitle($data->title);
        }

        if (null !== $data->data) {
            $object->setData($data->data);
        }

        return $this->processOwnerId($object);
    }
}
