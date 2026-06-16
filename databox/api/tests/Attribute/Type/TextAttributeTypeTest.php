<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextAttributeType;

class TextAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new TextAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ['', null],
            ['a', null],
            [' ', null],
            [0, ['Invalid value']],
            [1, ['Invalid value']],
            [false, ['Invalid value']],
            [true, ['Invalid value']],
        ];
    }
}
