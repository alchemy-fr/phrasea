<?php

declare(strict_types=1);

namespace App\Api\Mapper\Input;

use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: TemplateAttributeInput::class)]
class TemplateAttributeInputMapper extends AbstractInputMapper implements InputMapperInterface
{
    use AttributeInputTrait;

    public function __construct(private readonly AttributeAssigner $attributeAssigner)
    {
    }

    /**
     * @param TemplateAttributeInput $data
     */
    public function map(object $data, ?object $target, array $context = []): ?object
    {
        $isNew = null === $target;
        /** @var TemplateAttribute $object */
        $object = $target ?? new TemplateAttribute();

        if ($isNew) {
            $object->setTemplate($data->template);
            $object->setDefinition($this->getAttributeDefinitionFromInput(
                $data,
                $object->getTemplate()?->getWorkspace(),
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

        return $object;
    }
}
