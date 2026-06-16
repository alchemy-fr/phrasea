<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use PHPUnit\Framework\TestCase;

class DummyString implements \Stringable
{
    public function __construct(private readonly string $str)
    {
    }

    public function __toString(): string
    {
        return $this->str;
    }
}

abstract class AbstractAttributeTypeTest extends TestCase
{
    abstract protected function getType(): AttributeTypeInterface;

    /**
     * @dataProvider getValidationCases
     */
    public function testValidation($value, ?array $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->validate($type->normalizeValue($value)));
    }

    /**
     * @dataProvider getNormalizationCases
     */
    public function testNormalization($value, $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->normalizeValue($value));
    }

    /**
     * @dataProvider getConvertToDbValueCases
     */
    public function testConvertToDbValue($value, $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->convertToDbValue($type->normalizeValue($value)));
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

    public function getValidationCases(): array
    {
        return [];
    }

    public function getNormalizationCases(): array
    {
        return [
            'null' => [null, null],
            'object' => [new \stdClass(), new \stdClass()],
            'empty_array' => [[], []],
            'false' => [false, false],
            'true' => [true, true],
            'false_string' => ['false', 'false'],
            'true_string' => ['true', 'true'],
            'empty_string' => ['', null],
            'single_space' => [' ', null],
            'many_spaces' => ['   ', null],
            'left_trimmed_string' => ['   a', 'a'],
            'right_trimmed_string' => ['a    ', 'a'],
            '0' => [0, 0],
            '1' => [1, 1],
            '-1' => [-1, -1],
            '0_string' => ['0', '0'],
            '1_string' => ['1', '1'],
            '-1_string' => ['-1', '-1'],
            'foo' => ['foo', 'foo'],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            'null' => [null, null],
            'object' => [new \stdClass(), null],
            'empty_array' => [[], null],
            'non_empty_array' => [['a'], null],
            'false_string' => ['false', 'false'],
            'true_string' => ['true', 'true'],
            'false' => [false, 'false'],
            'true' => [true, 'true'],
            'empty_string' => ['', null],
            'single_space' => [' ', null],
            'many_spaces' => ['   ', null],
            'left_trimmed_string' => ['   a', 'a'],
            'right_trimmed_string' => ['a    ', 'a'],
            '0' => [0, '0'],
            '1' => [1, '1'],
            '-1' => [-1, -1],
            '0_string' => ['0', '0'],
            '1_string' => ['1', '1'],
            '-1_string' => ['-1', '-1'],
            'foo' => ['foo', 'foo'],
            'empty_stringable' => [new DummyString(''), null],
            'non_empty_stringable' => [new DummyString('foo'), 'foo'],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', ''],
            'single_space' => [' ', ' '],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', ''],
            'single_space' => [' ', ' '],
        ];
    }
}
