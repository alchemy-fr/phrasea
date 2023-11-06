<?php

declare(strict_types=1);

namespace App\Api\InputTransformer;

use App\Api\Model\Input\Attribute\AttributeInput;
use App\Attribute\AttributeAssigner;
use App\Entity\Core\Attribute;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class AttributeInputTransformer extends AbstractInputTransformer
{
    use AttributeInputTrait;

    public function __construct(private readonly AttributeAssigner $attributeAssigner)
    {
    }

    public function supports(string $resourceClass, object $data): bool
    {
        return Attribute::class === $resourceClass && $data instanceof AttributeInput;
    }

    /**
     * @param AttributeInput $data
     */
    public function transform(object $data, string $resourceClass, array $context = []): object|iterable
    {
        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);
        /** @var Attribute $object */
        $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? new Attribute();

        if ($isNew) {
            $object->setAsset($data->asset);
            $object->setDefinition($this->getAttributeDefinitionFromInput($data, null, $context));
        }

        $this->attributeAssigner->assignAttributeFromInput($object, $data);

        return $object;
    }
}
