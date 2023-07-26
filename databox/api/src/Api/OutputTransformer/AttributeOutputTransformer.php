<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\AttributeOutput;
use App\Attribute\AttributeTypeRegistry;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;
use App\Util\SecurityAwareTrait;

class AttributeOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;

    public function __construct(private readonly AttributeTypeRegistry $attributeTypeRegistry)
    {
    }


    public function supports(string $outputClass, object $data): bool
    {
        return AttributeOutput::class === $outputClass && $data instanceof AbstractBaseAttribute;
    }

    /**
     * @param AbstractBaseAttribute $data
     */
    public function transform(object $data, string $outputClass, array $context = []): object
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
}
