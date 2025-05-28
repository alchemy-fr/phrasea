<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\AttributeListInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\AttributeList\AttributeList;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributeListInputTransformer extends AbstractFileInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return AttributeList::class === $resourceClass && $data instanceof AttributeListInput;
    }

    /**
     * @param AttributeListInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var AttributeList $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new AttributeList();

        if (null !== $data->public) {
            $object->setPublic($data->public);
        }

        if (null !== $data->title) {
            $object->setTitle($data->title);
        }

        if (null !== $data->description) {
            $object->setDescription($data->description);
        }

        return $this->processOwnerId($object);
    }
}
