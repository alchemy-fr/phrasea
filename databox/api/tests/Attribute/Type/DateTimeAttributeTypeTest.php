<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\DateTimeAttributeType;

class DateTimeAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new DateTimeAttributeType();
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
        ];
    }

    public function getNormalizationCases(): array
    {
        return [
            ...parent::getNormalizationCases(),
            '1_int' => [1, new \DateTimeImmutable('1970-01-01T00:00:01+00:00')],
            ['2008', '2008'],
            ['2009', '2009'],
            [' 2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00+00:00')],
            [' 2008-01-12T12:13:00Z ', new \DateTimeImmutable('2008-01-12T12:13:00+00:00')],
            ['2008-01-12T12:13:01Z ', new \DateTimeImmutable('2008-01-12T12:13:01+00:00')],
            ['2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00+00:00')],
            ['2008-01-12T00:00:00Z', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            ['2008-01-12T00:00:00+00:00', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            ['2008-01-12', new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            [new \DateTimeImmutable('2008-01-12T00:00:00'), new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
            [new \DateTime('2008-01-12T00:00:00'), new \DateTimeImmutable('2008-01-12T00:00:00+00:00')],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            '1_int' => [1, '1970-01-01T00:00:01+00:00'],
            ['2008', '2008'],
            ['2009', '2009'],
            [' 2008-01-12T12:13:00Z', '2008-01-12T12:13:00+00:00'],
            [' 2008-01-12T12:13:00Z ', '2008-01-12T12:13:00+00:00'],
            ['2008-01-12T12:13:01Z ', '2008-01-12T12:13:01+00:00'],
            ['2008-01-12T12:13:00Z', '2008-01-12T12:13:00+00:00'],
            ['2008-01-12T00:00:00Z', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12T00:00:00+00:00', '2008-01-12T00:00:00+00:00'],
            ['2008-01-12', '2008-01-12T00:00:00+00:00'],
            [new \DateTimeImmutable('2008-01-12T00:00:00'), '2008-01-12T00:00:00+00:00'],
            [new \DateTime('2008-01-12T00:00:00'), '2008-01-12T00:00:00+00:00'],
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
            ['2008-01-12T12:13:00Z', \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2008-01-12T12:13:00Z')],
            ['2008-01-12T12:13:00Z', new \DateTimeImmutable('2008-01-12T12:13:00')],
            ['2008-01-12T12:13:00Z', new \DateTime('2008-01-12T12:13:00')],
        ];
    }

    public function getElasticsearchNormalizationCases(): array
    {
        return [
            ...parent::getElasticsearchNormalizationCases(),
            'empty' => ['', null],
            'single_space' => [' ', null],
        ];
    }
}
