<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Template\TemplateAttribute;

class TemplateAttributeInputProcessor extends AbstractInputProcessor
{
    use AttributeInputTrait;

    public function __construct(private readonly AttributeAssigner $attributeAssigner)
    {
    }

    /**
     * @param TemplateAttributeInput $data
     */
    protected function transform(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var TemplateAttribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new TemplateAttribute();

        if ($isNew) {
            $object->setTemplate($data->template);
            $object->setDefinition($this->getAttributeDefinitionFromInput(
                $data,
                $object->getTemplate() ? $object->getTemplate()->getWorkspace() : null,
                $context
            ));
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }
}
