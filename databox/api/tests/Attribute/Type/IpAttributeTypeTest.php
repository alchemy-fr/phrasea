<?php

declare(strict_types=1);

namespace App\Tests\Attribute\Type;

use App\Attribute\Type\AttributeTypeInterface;
use App\Attribute\Type\IpAttributeType;

class IpAttributeTypeTest extends AbstractAttributeTypeTest
{
    protected function getType(): AttributeTypeInterface
    {
        return new IpAttributeType();
    }

    public function getValidationCases(): array
    {
        return [
            [null, null],
            ['127.0.0.1', null],
            ['not-an-ip', null],
        ];
    }

    public function getConvertToDbValueCases(): array
    {
        return [
            [null, null],
            ['127.0.0.1', '127.0.0.1'],
            ['::1', '::1'],
            [1, '1'],
            [[], null],
        ];
    }

    public function getDenormalizationCases(): array
    {
        return [
            [null, null],
            ['', null],
            ['127.0.0.1', '127.0.0.1'],
        ];
    }
}
