<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\GeoPointAttributeType;

class GeoPointAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new GeoPointAttributeType();
    }

    public function getNormalizationCases(): array
    {
        return [
            [['lat' => 48.8, 'lng' => 2.32], '48.8,2.32'],
            ['2.32, 48.8', '2.32,48.8'],
            ['2.32  ,  48.8', '2.32,48.8'],
            ['2.32,48.8', '2.32,48.8'],
            ['0,0', '0,0'],
            ['0.0,0.01', '0,0.01'],
            ['0.00099999991,0.01', '0.001,0.01'],
            [null, null],
            ['', null],
            [' ', null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            ['48.8,2.32', ['lat' => 48.8, 'lng' => 2.32]],
            [null, null],
            ['', null],
            [' ', null],
        ];
    }
}
