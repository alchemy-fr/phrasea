<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\TextAttributeType;

class DummyString {
    private string $str;

    public function __construct(string $str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

class TextAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new TextAttributeType();
    }

    public function getNormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            [' ', ' '],
            [[], null],
            [false, null],
            [true, '1'],
            [new \stdClass(), null],
            [new DummyString(''), null],
            [new DummyString('foo'), 'foo'],
            ['foo', 'foo'],
            ['1', '1'],
            [1, '1'],
            [0, '0'],
        ];
    }
}
