<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\JsonAttributeType;

class JsonAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new JsonAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ['', null],
            ['{"a":1}', null],
            [' ', null],
            [0, ['Invalid value']],
            [false, ['Invalid value']],
            [true, ['Invalid value']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['{"a":1}', '{"a":1}'],
            ['[]', '[]'],
            ['[false]', '[false]'],
            ['1', '1'],
            ['1', '1'],
            ['true', 'true'],
            ['false', 'false'],
        ];
    }
}
