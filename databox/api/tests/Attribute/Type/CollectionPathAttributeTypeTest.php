<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\CollectionPathAttributeType;

class CollectionPathAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new CollectionPathAttributeType();
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['root/children', 'root/children'],
            [1, '1'],
            [[], null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['root/children', 'root/children'],
        ];
    }

    public function testValidationThrowsException(): void
    {
        $this->expectException(\LogicException::class);

        $this->getType()->validate('root/children');
    }

    public function testGetAggregationFieldThrowsException(): void
    {
        $this->expectException(\LogicException::class);

        $this->getType()->getAggregationField();
    }
}
