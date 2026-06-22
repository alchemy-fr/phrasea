<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\ColorAttributeType;

class ColorAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new ColorAttributeType();
    }

    #[\Override]
    public function getValidationCases(): array
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
    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['#fff', '#fff'],
            [' red ', 'red'],
        ];
    }
}
