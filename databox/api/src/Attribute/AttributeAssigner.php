<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AbstractExtendedAttributeInput;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;
use App\Util\LocaleUtils;

final readonly class AttributeAssigner
{
    public function __construct(private AttributeTypeRegistry $attributeTypeRegistry)
    {
    }

    public function assignAttributeFromInput(AbstractBaseAttribute $attribute, AbstractBaseAttributeInput $data): AbstractBaseAttribute
    {
        if ($data instanceof AbstractExtendedAttributeInput) {
            assert($attribute instanceof Attribute);
            if ($data->origin) {
                if (false !== $k = array_search($data->origin, Attribute::ORIGIN_LABELS, true)) {
                    $attribute->setOrigin($k);
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid origin value "%s", allowed ones are: %s', $data->origin, implode(', ', Attribute::ORIGIN_LABELS)));
                }
            } elseif (!$attribute->hasOrigin()) {
                $attribute->setOrigin(Attribute::ORIGIN_MACHINE);
            }

            if ($data->status) {
                if (false !== $k = array_search($data->status, Attribute::STATUS_LABELS, true)) {
                    $attribute->setStatus($k);
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid status value "%s", allowed ones are: %s', $data->status, implode(', ', Attribute::STATUS_LABELS)));
                }
            }
            $attribute->setOriginUserId($data->originUserId);
            $attribute->setOriginVendor($data->originVendor);
            $attribute->setOriginVendorContext($data->originVendorContext);
            if ($data->confidence) {
                $attribute->setConfidence($data->confidence);
            }
            $attribute->setCoordinates($data->coordinates);
        }

        if ($data->locale) {
            $attribute->setLocale(LocaleUtils::normalizeLocale($data->locale));
        }

        $type = $this->attributeTypeRegistry->getStrictType($attribute->getDefinition()->getFieldType());
        $value = $type->normalizeValue($data->value);

        $attribute->setValue($value);
        $attribute->setPosition($data->position ?? 0);

        return $attribute;
    }
}
