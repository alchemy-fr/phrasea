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

    #[\Override]
    public function getValidationCases(): array
    {
        // Validation should not be called for this type
        return [];
    }

    #[\Override]
    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['root/children', 'root/children'],
        ];
    }

    #[\Override]
    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            ['root/children', 'root/children'],
            ['root', 'root'],
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
