<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Attribute;

class AttributeInputProcessor extends AbstractInputProcessor
{
    use AttributeInputTrait;

    public function __construct(private readonly AttributeAssigner $attributeAssigner)
    {
    }

    /**
     * @param AttributeInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var Attribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);
            $object->setDefinition($this->getAttributeDefinitionFromInput($data, null, $context));
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }
}
