<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\JsonAttributeType;

class JsonAttributeTypeTest extends CodeAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new JsonAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            'foo' => ['foo', ['Invalid JSON: Syntax error']],
            ['{"a":1}', null],
            ['{"a"1}', ['Invalid JSON: Syntax error']],
            ['[]', null],
            ['[false]', null],
            ['true', null],
            ['false', null],
            ['1', null],
            ['0', null],
            ['42', null],
            ['-42', null],
            [1, ['Invalid value']],
            [0, ['Invalid value']],
            [42, ['Invalid value']],
            [-42, ['Invalid value']],
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
