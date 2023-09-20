<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\Template\TemplateAttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Template\TemplateAttribute;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class TemplateAttributeInputTransformer extends AbstractInputTransformer
{
    use AttributeInputTrait;

    public function __construct(private readonly AttributeAssigner $attributeAssigner)
    {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return TemplateAttribute::class === $resourceClass && $data instanceof TemplateAttributeInput;
    }

    /**
     * @param TemplateAttributeInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var TemplateAttribute $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new TemplateAttribute();

        if ($isNew) {
            $object->setTemplate($data->template);
            $object->setDefinition($this->getAttributeDefinitionFromInput(
                $data,
                $object->getTemplate()?->getWorkspace(),
                $context
            ));
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }
}
