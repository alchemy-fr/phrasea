<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Utils\SizeUtils;
use PHPUnit\Framework\TestCase;

class SizeUtilsTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testFormatSize(?string $expected, $size, bool $si): void
    {
        $this->assertEquals($expected, SizeUtils::formatSize($size, $si));
    }

    public function getCases(): array
    {
        return [
            [null, null, false],
            ['1.00 B', 1, false],
            ['1.00 B', 1, true],
            ['1.45 GiB', 1_551_859_712, false],
            ['5.00 kB', 5000, true],
            ['4.88 KiB', 5000, false],
            ['8,271.81 YiB', 10_000_000_000_000_000_000_000_000_000.0, false],
            ['10,000.00 YB', 10_000_000_000_000_000_000_000_000_000.0, true],
        ];
    }
}
