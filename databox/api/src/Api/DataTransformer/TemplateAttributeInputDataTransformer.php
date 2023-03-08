<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Template\TemplateAttribute;

class TemplateAttributeInputDataTransformer extends AbstractInputDataTransformer
{
    use AttributeInputTrait;

    private AttributeAssigner $attributeAssigner;

    public function __construct(AttributeAssigner $attributeAssigner)
    {
        $this->attributeAssigner = $attributeAssigner;
    }

    /**
     * @param TemplateAttributeInput $data
     */
    public function transform($data, string $to, array $context = [])
    {
        $this->validator->validate($data);

        $isNew = !isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
        /** @var TemplateAttribute $object */
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? new TemplateAttribute();

        if ($isNew) {
            $object->setTemplate($data->template);
            $object->setDefinition($this->getAttributeDefinitionFromInput($data, $object->getTemplate() ? $object->getTemplate()->getWorkspace() : null));
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
