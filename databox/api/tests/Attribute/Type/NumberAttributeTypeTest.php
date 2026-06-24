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

    #[\Override]
    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            [0, null],
            [1, null],
            [1.2, null],
            ['1', null],
            ['1.2', null],
            ['1e3', null],
            ['1E3', null],
            ['1e+3', null],
            ['1e-3', null],
            ['-1e3', null],
            ['+1e3', null],
            ['3.14e2', null],
            ['-3.14E-2', null],
            ['0e0', null],
            ['foo', ['Invalid number']],
            ['e3', ['Invalid number']],
            ['1e', ['Invalid number']],
            ['1e+', ['Invalid number']],
            ['1e-', ['Invalid number']],
            ['1e--3', ['Invalid number']],
            ['1ee3', ['Invalid number']],
            ['1.2.3e4', ['Invalid number']],
            ['--1e3', ['Invalid number']],
            ['+-1e3', ['Invalid number']],
            ['1 e3', ['Invalid number']],
            [true, ['Invalid number']],
        ];
    }

    #[\Override]
    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            '0_string' => ['0', 0],
            '1_string' => ['1', 1],
            '-1_string' => ['-1', -1],
            ['1.2', 1.2],
            ['1e3', 1000.0],
            ['1e-3', 0.001],
            ['-3.14E-2', -0.0314],
            [1.2, 1.2],
            [-1.2, -1.2],
        ];
    }

    #[\Override]
    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['1.2', '1.2'],
            ['1e3', '1000'],
            ['1e-3', '0.001'],
            ['-3.14E-2', '-0.0314'],
            [1.2, '1.2'],
            [-1.2, '-1.2'],
        ];
    }

    #[\Override]
    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['1', 1],
            ['1.2', 1.2],
            ['1e3', 1000.0],
            ['1e-3', 0.001],
            ['-3.14E-2', -0.0314],
            ['1e+', null],
            ['foo', null],
        ];
    }

    #[\Override]
    public function getElasticsearchNormalizationCases(): array
    {
        return [
            [null, null],
            ['1', 1],
            ['1.2', 1.2],
            ['-1.2', -1.2],
            ['1e3', 1000.0],
            ['1e-3', 0.001],
            ['-3.14E-2', -0.0314],
            ['1e+', null],
            ['foo', null],
            ['', null],
            [' ', null],
        ];
    }
}
