<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AttributeOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\Attribute;

class AttributeOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry)
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    /**
     * @param Attribute $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $type = $this->attributeTypeRegistry->getStrictType($object->getDefinition()->getFieldType());

        $output = new AttributeOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $output->asset = $object->getAsset();
        $values = $object->getValues();
        $output->value = $values ? array_map(function (?string $v) use ($type) {
            return $type->denormalizeValue($v);
        }, $object->getValues()) : $type->denormalizeValue($object->getValue());
        $output->multiple = null !== $values;
        $output->highlight = $object->getHighlights() ?? $object->getHighlight();
        $output->origin = $object->getOriginLabel();
        $output->originUserId = $object->getOriginUserId();
        $output->originVendor = $object->getOriginVendor();
        $output->originVendorContext = $object->getOriginVendorContext();
        $output->status = $object->getStatusLabel();
        $output->confidence = $object->getConfidence();
        $output->coordinates = $object->getCoordinates();
        $output->locale = $object->getLocale();
        $output->definition = $object->getDefinition();
        $output->position = $object->getPosition();

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeOutput::class === $to && $data instanceof Attribute;
    }
}
