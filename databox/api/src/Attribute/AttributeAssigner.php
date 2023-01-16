<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AbstractAttributeInput;
use App\Entity\Core\Attribute;
use App\Util\LocaleUtils;
use InvalidArgumentException;

class AttributeAssigner
{
    private AttributeTypeRegistry $attributeTypeRegistry;

    public function __construct(AttributeTypeRegistry $attributeTypeRegistry)
    {
        $this->attributeTypeRegistry = $attributeTypeRegistry;
    }

    public function assignAttributeFromInput(Attribute $attribute, AbstractAttributeInput $data): Attribute
    {
        if ($data->origin) {
            if (false !== $k = array_search($data->origin, Attribute::ORIGIN_LABELS, true)) {
                $attribute->setOrigin($k);
            } else {
                throw new InvalidArgumentException(sprintf('Invalid origin value "%s", allowed ones are: %s', $data->origin, implode(', ', Attribute::ORIGIN_LABELS)));
            }
        } elseif (!$attribute->hasOrigin()) {
            $attribute->setOrigin(Attribute::ORIGIN_MACHINE);
        }

        if ($data->status) {
            if (false !== $k = array_search($data->status, Attribute::STATUS_LABELS, true)) {
                $attribute->setStatus($k);
            } else {
                throw new InvalidArgumentException(sprintf('Invalid status value "%s", allowed ones are: %s', $data->status, implode(', ', Attribute::STATUS_LABELS)));
            }
        }

        if ($data->locale) {
            $attribute->setLocale(LocaleUtils::normalizeLocale($data->locale));
        }

        $type = $this->attributeTypeRegistry->getStrictType($attribute->getDefinition()->getFieldType());
        $value = $type->normalizeValue($data->value);

        $attribute->setValue($value);
        $attribute->setOriginUserId($data->originUserId);
        $attribute->setOriginVendor($data->originVendor);
        $attribute->setOriginVendorContext($data->originVendorContext);
        $attribute->setPosition($data->position ?? 0);
        if ($data->confidence) {
            $attribute->setConfidence($data->confidence);
        }
        $attribute->setCoordinates($data->coordinates);

        return $attribute;
    }
}
