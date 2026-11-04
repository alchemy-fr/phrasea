<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\ColorAttributeType;

class ColorAttributeTypeTest extends AbstractAttributeTypeTestCase
{
    protected function getType(): AttributeTypeInterface
    {
        return new ColorAttributeType();
    }

    #[\Override]
    public static function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            ['#fff', null],
            ['red', null],
            [0, ['Invalid value']],
            [false, ['Invalid value']],
            [true, ['Invalid value']],
        ];
    }

    #[\Override]
    public static function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['#fff', '#fff'],
            [' red ', 'red'],
        ];
    }
}
