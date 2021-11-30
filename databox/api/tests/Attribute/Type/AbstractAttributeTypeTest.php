<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractAttributeTypeTest extends TestCase
{
    abstract protected function getType(): AttributeTypeInterface;

    /**
     * @dataProvider getNormalizationCases
     */
    public function testNormalization($value, $expected): void
    {
        $type = $this->getType();

        $this->assertEquals($expected, $type->normalizeValue($value));
    }

    public function getNormalizationCases(): array
    {
        return [];
    }
}
