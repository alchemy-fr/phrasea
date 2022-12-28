<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\BooleanAttributeType;

class BooleanAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new BooleanAttributeType();
    }

    public function getNormalizationCases(): array
    {
        return [
            [false, '0'],
            [null, null],
            ['', null],
            [' ', null],
            ['false', '0'],
            ['true', '1'],
            ['y', '1'],
            ['Y', '1'],
            ['1', '1'],
            ['yes', '1'],
            ['Yes', '1'],
            ['YES', '1'],
            ['ON', '1'],
            ['On', '1'],
            ['on', '1'],
            ['TRUE', '1'],
            ['true', '1'],
            ['false', '0'],
            ['FALSE', '0'],
            ['no', '0'],
            ['No', '0'],
            ['NO', '0'],
            ['OFF', '0'],
            ['Off', '0'],
            ['0', '0'],
            [true, '1'],
            ['foo', null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            [' ', null],
            ['1', true],
            ['0', false],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            [null, null],
            ['0', false],
            ['1', true],
        ];
    }

    public function getElasticsearchDenormalizationCases(): array
    {
        return [
            [null, null],
            [false, '0'],
            [true, '1'],
        ];
    }
}
