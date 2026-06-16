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

    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            ['2008', ['Invalid date']],
            ['2009', ['Invalid date']],
            ['foo', ['Invalid date']],
            ['1', ['Invalid date']],
            ['2008-01-12T12:13:00Z', null],
            ['2008-01-12T00:00:00Z', null],
            ['2008-01-12T00:00:00+00:00', null],
            ['2008-01-12', null],
            [new \DateTimeImmutable('2008-01-12T00:00:00'), null],
            [new \DateTime('2008-01-12T00:00:00'), null],
            ['1997/1997_01/', ['Invalid date']],
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            '1' => [1, new \DateTimeImmutable('1970-01-01T00:00:01+00:00')],
            ['2008', '2008'],
            ['2009', '2009'],
            ['2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00+00:00')],
            ['2008-01-12T00:00:00Z', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            ['2008-01-12T00:00:00+00:00', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            ['2008-01-12', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            [new \DateTimeImmutable('2008-01-12T00:00:00'), new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            [new \DateTime('2008-01-12T00:00:00'), new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            ['1997/1997_01/', '1997/1997_01/'],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            '1' => [1, '1970-01-01T00:00:01+00:00'],
            ['2008', '2008'],
            ['2009', '2009'],
            ['2008-01-12T12:13:00Z', '2008-01-12T12:13:00+00:00'],
            ['2008-01-12T00:00:00Z', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12T00:00:00+00:00', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12', '2008-01-12T00:00:00+00:00'],
            [new \DateTimeImmutable('2008-01-12T00:00:00'), '2008-01-12T00:00:00+00:00'],
            [new \DateTime('2008-01-12T00:00:00'), '2008-01-12T00:00:00+00:00'],
            ['1997/1997_01/', '1997/1997_01/'],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
            ['2008', null],
            ['2009', null],
            ['foo', null],
            ['1', null],
            [' 2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00')],
            [' 2008-01-12T12:13:00Z ', new \DateTimeImmutable('2008-01-12T12:13:00')],
            ['2008-01-12T12:13:00Z ', new \DateTimeImmutable('2008-01-12T12:13:00')],
            ['2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00')],
            ['2008-01-12T12:13:00Z', new \DateTime('2008-01-12T12:13:00')],
        ];
    }
}
