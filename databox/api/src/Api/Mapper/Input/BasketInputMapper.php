<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\BasketInput;
use App\Api\Processor\WithOwnerIdProcessorTrait;
use App\Entity\Basket\Basket;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: BasketInput::class)]
class BasketInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use WithOwnerIdProcessorTrait;

    /**
     * @param BasketInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        /** @var Basket $object */
        $object = $target ?? new Basket();

        if (null !== $data->name) {
            $object->setName($data->name);
        }

        if (null !== $data->description) {
            $object->setDescription($data->description);
        }

        return $this->processOwnerId($object);
    }
}
