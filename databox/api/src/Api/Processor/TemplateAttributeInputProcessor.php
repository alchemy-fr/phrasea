<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Metadata\Operation;
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
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $this->validator->validate($data);

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

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof TemplateAttribute) {
            return false;
        }

        return TemplateAttribute::class === $to && TemplateAttributeInput::class === ($context['input']['class'] ?? null);
    }
}
