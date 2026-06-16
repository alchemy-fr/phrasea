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
            ...parent::getValidationCases(),
            [0, null],
            [1, null],
            [1.2, null],
            ['1', null],
            ['1.2', null],
            ['foo', ['Invalid number']],
            [true, ['Invalid number']],
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            '0_string' => ['0', 0],
            '1_string' => ['1', 1],
            '-1_string' => ['-1', -1],
            ['1.2', 1.2],
            [1.2, 1.2],
            [-1.2, -1.2],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['1.2', '1.2'],
            [1.2, '1.2'],
            [-1.2, '-1.2'],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
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
