<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Attribute;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AttributeInput::class)]
class AttributeInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use AttributeInputTrait;

    public function __construct(
        private readonly AttributeAssigner $attributeAssigner)
    {
    }

    /**
     * @param AttributeInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var Attribute $object */
        $object = $target ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);
            $object->setDefinition($this->getAttributeDefinitionFromInput(
                $data,
                $object->getAsset()?->getWorkspace(),
                $context
            ));
        }

        $normalizedValue = $this->attributeAssigner->normalizeValue($object->getDefinition(), $data->value);
        if (null === $normalizedValue) {
            if (!$isNew) {
                $this->em->remove($object);
            }

            return null;
        }
        $this->attributeAssigner->assignAttributeFromInput($object, $data, $normalizedValue);

        $this->attributeAssigner->resetAssetAttributesCache($object->getAsset());

        return $object;
    }
}
