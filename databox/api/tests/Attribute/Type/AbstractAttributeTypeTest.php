<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractAttributeTypeTest extends TestCase
{
    abstract protected function getType(): AttributeTypeInterface;

    /**
     * @dataProvider getValidationCases
     */
    public function testValidation($value, ?array $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->validate($value));
    }

    /**
     * @dataProvider getNormalizationCases
     */
    public function testNormalization($value, ?string $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->normalizeValue($value));
    }

    /**
     * @dataProvider getDenormalizationCases
     */
    public function testDenormalization(?string $value, $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->denormalizeValue($value));
    }

    /**
     * @dataProvider getElasticsearchNormalizationCases
     */
    public function testElasticsearchNormalization(?string $value, $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->normalizeElasticsearchValue($value));
    }

    /**
     * @dataProvider getElasticsearchDenormalizationCases
     */
    public function testElasticsearchDenormalization($value, ?string $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->denormalizeElasticsearchValue($value));
    }

    public function getValidationCases(): array
    {
        return [];
    }

    public function getNormalizationCases(): array
    {
        return [];
    }

    public function getDenormalizationCases(): array
    {
        return [];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [];
    }

    public function getElasticsearchDenormalizationCases(): array
    {
        return [];
    }
}
