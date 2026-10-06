<?php

declare(strict_types=1);

namespace App\Tests\Unit\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\CollectionPathAttributeType;
use PHPUnit\Framework\Attributes\DataProvider;

class CollectionPathAttributeTypeTest extends AbstractAttributeTypeTestCase
{
    protected function getType(): AttributeTypeInterface
    {
        return new CollectionPathAttributeType();
    }

    #[\Override]
    #[DataProvider('getValidationCases')]
    public function testValidation($value, ?array $expected): void
    {
        $this->expectException(\LogicException::class);
        $this->getType()->validate($value);
    }

    #[\Override]
    public static function getValidationCases(): array
    {
        // Validation must never be called for this type
        return [
            'path' => ['/a/b', null],
        ];
    }

    #[\Override]
    public static function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['root/children', 'root/children'],
        ];
    }

    #[\Override]
    public static function getDenormalizationCases(): array
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
