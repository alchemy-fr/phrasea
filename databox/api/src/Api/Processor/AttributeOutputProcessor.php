<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use App\Api\Model\Output\AttributeOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;

class AttributeOutputProcessor extends AbstractSecurityProcessor
{
    public function __construct(private readonly AttributeTypeRegistry $attributeTypeRegistry)
    {
    }

    /**
     * @param AbstractBaseAttribute $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $type = $this->attributeTypeRegistry->getStrictType($data->getDefinition()->getFieldType());

        $output = new AttributeOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());
        $values = $data->getValues();
        $output->value = $values ? array_map(fn (?string $v) => $type->denormalizeValue($v), $data->getValues()) : $type->denormalizeValue($data->getValue());
        $output->multiple = null !== $values;

        $output->locale = $data->getLocale();
        $output->position = $data->getPosition();
        $output->definition = $data->getDefinition();

        if ($data instanceof Attribute) {
            $output->asset = $data->getAsset();
            $output->highlight = $data->getHighlights() ?? $data->getHighlight();
            $output->origin = $data->getOriginLabel();
            $output->originUserId = $data->getOriginUserId();
            $output->originVendor = $data->getOriginVendor();
            $output->originVendorContext = $data->getOriginVendorContext();
            $output->status = $data->getStatusLabel();
            $output->confidence = $data->getConfidence();
            $output->coordinates = $data->getCoordinates();
        }

        return $output;
    }

    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        return AttributeOutput::class === $to && $data instanceof AbstractBaseAttribute;
    }
}
