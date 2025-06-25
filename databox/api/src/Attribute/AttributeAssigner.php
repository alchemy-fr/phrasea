<?php

declare(strict_types=1);

namespace App\Attribute;

use Alchemy\CoreBundle\Util\LocaleUtil;
use App\Api\Model\Input\Attribute\AbstractBaseAttributeInput;
use App\Api\Model\Input\Attribute\AbstractExtendedAttributeInput;
use App\Entity\Core\AbstractBaseAttribute;
use App\Entity\Core\Attribute;

class AttributeAssigner
{
    public function __construct(private AttributeTypeRegistry $attributeTypeRegistry)
    {
    }

    public function assignAttributeFromInput(AbstractBaseAttribute $attribute, AbstractBaseAttributeInput $data): void
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
            $attribute->setAssetAnnotations($data->annotations);
        }

        if ($data->locale) {
            $attribute->setLocale(LocaleUtil::normalizeLocale($data->locale));
        }

        $type = $this->attributeTypeRegistry->getStrictType($attribute->getDefinition()->getFieldType());
        $value = $type->normalizeValue($data->value);

        if (null === $value) {
            throw new InvalidAttributeValueException(sprintf('Normalized "%s" value is NULL (from: "%s")', $type::getName(), get_debug_type($data->value)));
        }

        $attribute->setValue($value);
        $attribute->setPosition($data->position ?? 0);
    }
}
