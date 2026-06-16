<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\NumberAttributeType;

class NumberAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new NumberAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            [0, null],
            [1, null],
            [1.2, null],
            ['1', null],
            ['1.2', null],
            ['foo', ['Invalid number']],
            [true, ['Invalid number']],
            [null, ['Invalid number']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['', ''],
            ['1', '1'],
            ['1.2', '1.2'],
            [1, '1'],
            [0, '0'],
            ['0', '0'],
            [1.2, '1.2'],
            [-1.2, '-1.2'],
            [true, '1'],
            [false, '0'],
            ['foo', 'foo'],
            [[], null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['1', 1],
            ['1.2', 1.2],
            ['foo', null],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            [null, null],
            ['1', 1.0],
            ['1.2', 1.2],
            ['-1.2', -1.2],
            ['foo', null],
            ['', null],
            [' ', null],
        ];
    }
}
