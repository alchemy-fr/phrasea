<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\IdAttributeType;

class IdAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new IdAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            ['', null],
            ['asset-id', null],
            ['asset id', ['ID cannot contain spaces']],
            [0, ['Invalid value']],
            [false, ['Invalid value']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['', ''],
            ['asset-id', 'asset-id'],
            ['asset id', 'asset id'],
            [1, '1'],
            [[], null],
        ];
    }
}
