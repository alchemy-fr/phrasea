<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\BasketInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Basket\Basket;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class BasketInputTransformer extends AbstractFileInputTransformer
{
    use WithOwnerIdProcessorTrait;

    public function supports(string $resourceClass, object $data): bool
    {
        return Basket::class === $resourceClass && $data instanceof BasketInput;
    }

    /**
     * @param BasketInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        /** @var Basket $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new Basket();

        if (null !== $data->title) {
            $object->setTitle($data->title);
        }

        if (null !== $data->description) {
            $object->setDescription($data->description);
        }

        return $this->processOwnerId($object);
    }
}
