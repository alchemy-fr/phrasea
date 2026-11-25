<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Filter\AssociationIdentifierFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AssociationIdentifierFilterTest extends TestCase
{
    #[DataProvider('getUuidCases')]
    public function testIsUuid(bool $expected, string $value): void
    {
        $this->assertSame($expected, AssociationIdentifierFilter::isUuid($value));
    }

    public static function getUuidCases(): array
    {
        return [
            [true, '0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7d'],
            [true, '0B7E2E8C-5C47-4F3A-9A8E-0E2D1F5B6C7D'],
            [true, '0b7e2e8c5c474f3a9a8e0e2d1f5b6c7d'],
            [true, '{0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7d}'],
            [true, '00000000-0000-0000-0000-000000000000'],
            [false, ''],
            [false, 'abc'],
            [false, '42'],
            [false, '0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7'],
            [false, '0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7dd'],
            [false, 'zb7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7d'],
            [false, '{0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7d'],
            [false, "0b7e2e8c-5c47-4f3a-9a8e-0e2d1f5b6c7d\n"],
        ];
    }
}
