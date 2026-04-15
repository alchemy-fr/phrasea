<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\ProfileInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Profile\Profile;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ProfileInputTransformer extends AbstractFileInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return Profile::class === $resourceClass && $data instanceof ProfileInput;
    }

    /**
     * @param ProfileInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var Profile $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new Profile();

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
