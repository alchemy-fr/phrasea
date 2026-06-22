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

        $normalized = $type->normalizeValue($value);
        if (null === $normalized) {
            $this->assertNull($expected, 'Normalization led to a NULL value but expected errors were not NULL');
        } else {
            $errors = $type->validate($normalized);
            $this->assertSame($expected, $errors);
        }
    }

    /**
     * @dataProvider getNormalizationCases
     */
    public function testNormalization($value, $expected): void
    {
        $type = $this->getType();

        $normalizedValue = $type->normalizeValue($value);
        if (is_object($expected)) {
            $this->assertEquals($expected, $normalizedValue);
        } else {
            $this->assertSame($expected, $normalizedValue);
        }
    }

    /**
     * @dataProvider getConvertToDbValueCases
     */
    public function testConvertToDbValue($value, $expected): void
    {
        $type = $this->getType();

        $this->assertSame($expected, $type->convertToDbValue($type->normalizeValue($value)));
    }

    /**
     * @dataProvider getDenormalizationCases
     */
    public function testDenormalization(?string $value, $expected): void
    {
        $type = $this->getType();

        $denormalizedValue = $type->denormalizeValue($value);
        if (is_object($expected)) {
            $this->assertEquals($expected, $denormalizedValue);
        } else {
            $this->assertSame($expected, $denormalizedValue);
        }
    }

    /**
     * @dataProvider getElasticsearchNormalizationCases
     */
    public function testElasticsearchNormalization(?string $value, $expected): void
    {
        $type = $this->getType();

        $this->assertSame($expected, $type->normalizeElasticsearchValue($value));
    }

    public function getValidationCases(): array
    {
        return [
            'null' => [null, null],
            'empty_string' => ['', null],
            'single_space' => [' ', null],
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            'null' => [null, null],
            'null_string' => ['null', 'null'],
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
            '0_int' => [0, 0],
            '1_int' => [1, 1],
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
            '0_int' => [0, '0'],
            '1_int' => [1, '1'],
            '-1_int' => [-1, '-1'],
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
