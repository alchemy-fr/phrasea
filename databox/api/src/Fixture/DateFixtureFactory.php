<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Entity\Core\Attribute;

class DateFixtureFactory
{
    public static function createDateAttribute(\DateTimeInterface $value): Attribute
    {
        $attribute = new Attribute();
        $attribute->setValue($value->format('Y-m-d'));

        return $attribute;
    }

    public static function createDateTimeAttribute(\DateTimeInterface $value): Attribute
    {
        $attribute = new Attribute();
        $attribute->setValue($value->format(\DateTimeInterface::ATOM));

        return $attribute;
    }
}
