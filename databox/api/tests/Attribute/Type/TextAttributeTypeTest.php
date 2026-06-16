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
            ...parent::getValidationCases(),
            'foo' => ['foo', null],
            [0, ['Invalid value']],
            [1, ['Invalid value']],
            [-1, ['Invalid value']],
            [false, ['Invalid value']],
            [true, ['Invalid value']],
            [[], ['Invalid value']],
            [[true], ['Invalid value']],
            [1.0, ['Invalid value']],
        ];
    }
}
