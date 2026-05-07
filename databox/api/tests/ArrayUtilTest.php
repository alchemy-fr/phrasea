<?php

namespace App\Tests;

use App\Util\ArrayUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArrayUtilTest extends TestCase
{
    /**
     * @dataProvider arraysAreSameProvider
     */
    public function testArraysAreSame(array $a, array $b, bool $expected): void
    {
        $this->assertSame($expected, ArrayUtil::arrayAreSame($a, $b));
    }

    public static function arraysAreSameProvider(): iterable
    {
        yield [
            [], [], true,
        ];

        yield [
            ['a' => 'a', 'b' => 'b'], ['a' => 'a', 'b' => 'b'], true,
        ];

        yield [
            ['a' => 'a', 'b' => 'b'], ['a' => 'a', 'b' => 'c'], false,
        ];

        yield [
            ['a' => 'a', 'b' => ['c']], ['a' => 'a', 'b' => ['c']], true,
        ];

        yield [
            ['a' => 'a', 'b' => ['c']], ['a' => 'a', 'b' => ['d']], false,
        ];

        yield [
            ['a' => 'a', 'b' => ['d', 'c']], ['a' => 'a', 'b' => ['c', 'd']], true,
        ];

        yield [
            ['a' => 'a', 'b' => [
                [3, 4], [1, 2],
            ]], ['a' => 'a', 'b' => [
                [2, 1], [4, 3],
            ]], true,
        ];
    }
}
