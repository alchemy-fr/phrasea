<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DurationAttributeType;

class DurationAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new DurationAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            [0, null],
            ['120', null],
            ['12.5', null],
            [12.5, null],
            [-0, null],
            [-1, null],
            ['foo', ['Invalid number']],
            [null, ['Invalid number']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['120', '120'],
            [120, '120'],
            [12.5, '12.5'],
            [-12.5, '-12.5'],
            [[], null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['0', 0],
            ['120', 120],
            ['12.5', 12.5],
            ['-12.5', -12.5],
            ['foo', null],
        ];
    }
}
