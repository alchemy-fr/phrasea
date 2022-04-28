<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Api\Model\Input\Attribute\AbstractAttributeInput;
use App\Entity\Core\Attribute;

class AttributeAssigner
{
    public function assignAttributeFromInput(Attribute $attribute, AbstractAttributeInput $data): Attribute
    {
        if ($data->origin) {
            if (false !== $k = array_search($data->origin, Attribute::ORIGIN_LABELS, true)) {
                $attribute->setOrigin($k);
            }
        }
        if ($data->status) {
            if (false !== $k = array_search($data->status, Attribute::STATUS_LABELS, true)) {
                $attribute->setStatus($k);
            }
        }

        if ($data->locale) {
            $attribute->setLocale($data->locale);
        }

        $value = null === $data->value ? null : (string) $data->value;
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
