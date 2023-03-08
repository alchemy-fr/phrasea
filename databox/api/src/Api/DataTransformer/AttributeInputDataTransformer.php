<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Attribute;

class AttributeInputDataTransformer extends AbstractInputDataTransformer
{
    use AttributeInputTrait;

    private AttributeAssigner $attributeAssigner;

    public function __construct(AttributeAssigner $attributeAssigner)
    {
        $this->attributeAssigner = $attributeAssigner;
    }

    /**
     * @param AttributeInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Attribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);
            $object->setDefinition($this->getAttributeDefinitionFromInput($data, null));
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Attribute) {
            return false;
        }

        return Attribute::class === $to && AttributeInput::class === ($context['input']['class'] ?? null);
    }
}
