<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\KeywordAttributeType;

class KeywordAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new KeywordAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ['', null],
            ['a', null],
            [' ', null],
            [0, ['Invalid value']],
            [false, ['Invalid value']],
            [true, ['Invalid value']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['', ''],
            [' ', ' '],
            ['foo', 'foo'],
            ['1', '1'],
            [1, '1'],
            [0, '0'],
            [true, '1'],
            [[], null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['0', '0'],
            ['foo', 'foo'],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            [null, null],
            ['foo', 'foo'],
        ];
    }
}
