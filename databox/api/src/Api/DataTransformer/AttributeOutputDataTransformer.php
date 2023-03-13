<?php

declare(strict_types=1);

namespace App\Api\DataTransformer;

use App\Api\Model\Output\AttributeOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;

class AttributeOutputDataTransformer extends AbstractSecurityDataTransformer
{
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry)
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    /**
     * @param AbstractBaseAttribute $object
     */
    public function transform($object, string $to, array $context = [])
    {
        $type = $this->attributeTypeRegistry->getStrictType($object->getDefinition()->getFieldType());

        $output = new AttributeOutput();
        $output->setCreatedAt($object->getCreatedAt());
        $output->setUpdatedAt($object->getUpdatedAt());
        $output->setId($object->getId());
        $values = $object->getValues();
        $output->value = $values ? array_map(function (?string $v) use ($type) {
            return $type->denormalizeValue($v);
        }, $object->getValues()) : $type->denormalizeValue($object->getValue());
        $output->multiple = null !== $values;

        $output->locale = $object->getLocale();
        $output->position = $object->getPosition();
        $output->definition = $object->getDefinition();

        if ($object instanceof Attribute) {
            $output->asset = $object->getAsset();
            $output->highlight = $object->getHighlights() ?? $object->getHighlight();
            $output->origin = $object->getOriginLabel();
            $output->originUserId = $object->getOriginUserId();
            $output->originVendor = $object->getOriginVendor();
            $output->originVendorContext = $object->getOriginVendorContext();
            $output->status = $object->getStatusLabel();
            $output->confidence = $object->getConfidence();
            $output->coordinates = $object->getCoordinates();
        }

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeOutput::class === $to && $data instanceof AbstractBaseAttribute;
    }
}
