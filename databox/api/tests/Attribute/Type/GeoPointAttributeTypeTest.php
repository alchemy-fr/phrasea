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

    public function getValidationCases(): array
    {
        return [
            [null, ['Invalid Geo point']],
            ['1,1', null],
            ['1.0,1', null],
            ['1.0,1.0', null],
            ['1.0, 1.0', null],
            ['', ['Invalid Geo point']],
            ['11', ['Invalid Geo point']],
            ['1?', ['Invalid Geo point']],
            ['a', ['Invalid Geo point']],
            ['a,a', ['Invalid Geo point']],
            ['1,1.', ['Invalid Geo point']],
            ['1.,1', ['Invalid Geo point']],
            ['1,.1', ['Invalid Geo point']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [['lat' => 48.8, 'lng' => 2.32], '48.8,2.32'],
            ['2.32, 48.8', '2.32,48.8'],
            ['2.32  ,  48.8', '2.32,48.8'],
            ['2.32,48.8', '2.32,48.8'],
            ['0,0', '0,0'],
            ['0.0,0.01', '0,0.01'],
            ['0.00099999991,0.01', '0.001,0.01'],
            ['0.00099999991 0.01', '0.001,0.01'],
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
