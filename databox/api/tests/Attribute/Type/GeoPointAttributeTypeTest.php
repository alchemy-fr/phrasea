<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\GeoPointAttributeType;
use App\Model\GeoPoint;

class GeoPointAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new GeoPointAttributeType();
    }

    #[\Override]
    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            ['1,1', null],
            ['1.0,1', null],
            ['1.0,1.0', null],
            ['1.0, 1.0', null],
            ['11', ['Invalid Geo point']],
            ['1?', ['Invalid Geo point']],
            ['a', ['Invalid Geo point']],
            ['a,a', ['Invalid Geo point']],
            ['1,1.', ['Invalid Geo point']],
            ['1.,1', ['Invalid Geo point']],
            ['1,.1', ['Invalid Geo point']],
        ];
    }

    #[\Override]
    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            [['lat' => 48.8, 'lng' => 2.32], new GeoPoint(48.8, 2.32)],
            ['2.32, 48.8', new GeoPoint(2.32, 48.8)],
            ['2.32  ,  48.8', new GeoPoint(2.32, 48.8)],
            ['2.32,48.8', new GeoPoint(2.32, 48.8)],
            ['2.32;48.8', new GeoPoint(2.32, 48.8)],
            ['0,0', new GeoPoint(0, 0)],
            ['0.0,0.01', new GeoPoint(0, 0.01)],
            ['0.00099999991,0.01', new GeoPoint(0.00099999991, 0.01)],
            ['0.00099999991 0.01', new GeoPoint(0.00099999991, 0.01)],
        ];
    }

    #[\Override]
    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            [['lat' => 48.8, 'lng' => 2.32], '48.8,2.32'],
            ['2.32, 48.8', '2.32,48.8'],
            ['2.32  ,  48.8', '2.32,48.8'],
            ['2.32,48.8', '2.32,48.8'],
            ['2.32;48.8', '2.32,48.8'],
            ['0,0', '0,0'],
            ['0.0,0.01', '0,0.01'],
            ['0.00099999991,0.01', '0.001,0.01'],
            ['0.00099999991 0.01', '0.001,0.01'],
        ];
    }

    #[\Override]
    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'null' => [null, null],
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['48.8,2.32', new GeoPoint(48.8, 2.32)],
        ];
    }

    #[\Override]
    public function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['0.00099999991,0.01', ['lat' => 0.00099999991, 'lon' => 0.01]],
            ['0.00099999991 0.01', ['lat' => 0.00099999991, 'lon' => 0.01]],
            ['48.8,2.32', ['lat' => 48.8, 'lon' => 2.32]],
            ['48.8, 2.32', ['lat' => 48.8, 'lon' => 2.32]],
            ['-48.8, 2.32', ['lat' => -48.8, 'lon' => 2.32]],
            ['-48.8, -2.32', ['lat' => -48.8, 'lon' => -2.32]],
            ['48.8, -2.32', ['lat' => 48.8, 'lon' => -2.32]],
            ['0, 0', ['lat' => 0.0, 'lon' => 0.0]],
        ];
    }
}
