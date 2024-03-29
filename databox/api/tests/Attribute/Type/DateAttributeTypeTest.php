<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateAttributeType;

class DateAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new DateAttributeType();
    }

    public function getNormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            [' ', null],
            ['2008', null],
            ['2009', null],
            ['foo', null],
            ['1', null],
            ['2008-01-12T12:13:00Z', '2008-01-12T12:13:00+00:00'],
            ['2008-01-12T00:00:00Z', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12T00:00:00+00:00', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12', '2008-01-12T00:00:00+00:00'],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            [' ', null],
            ['2008', null],
            ['2009', null],
            ['foo', null],
            ['1', null],
            ['2008-01-12T12:13:00Z', '2008-01-12'],
        ];
    }
}
