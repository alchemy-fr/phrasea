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

    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            'null' => [null, null],
            'empty_string' => ['', ['Invalid boolean']],
            'single_space' => [' ', ['Invalid boolean']],
            [false, null],
            [true, null],
            [1, null],
            [0, null],
            ['true', null],
            ['false', null],
            ['yes', null],
            ['Yes', null],
            ['YES', null],
            ['ON', null],
            ['On', null],
            ['on', null],
            ['TRUE', null],
            ['true', null],
            ['false', null],
            ['FALSE', null],
            ['no', null],
            ['No', null],
            ['NO', null],
            ['OFF', null],
            ['Off', null],
            ['0', null],
            ['a', ['Invalid boolean']],
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            'false_string' => ['false', '0'],
            'true_string' => ['true', '1'],
            'false' => [false, '0'],
            'true' => [true, '1'],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            'false_string' => ['false', '0'],
            'true_string' => ['true', '1'],
            'false' => [false, '0'],
            'true' => [true, '1'],
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
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['1', true],
            ['0', false],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['0', false],
            ['1', true],
        ];
    }
}
