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
            ...parent::getValidationCases(),
            ['asset-id', null],
            ['88065897-db30-44e3-91d9-762e41d2c258', null],
            ['asset id', ['ID cannot contain spaces']],
            [0, ['Invalid value']],
            [false, ['Invalid value']],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['88065897-db30-44e3-91d9-762e41d2c258', '88065897-db30-44e3-91d9-762e41d2c258'],
            ['asset-id', 'asset-id'],
            ['asset id', 'asset id'],
        ];
    }
}
