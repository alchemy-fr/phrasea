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

    #[\Override]
    public function getValidationCases(): array
    {
        return [
            ...parent::getValidationCases(),
            ['127.0.0.1', null],
            ['not-an-ip', ['Invalid IP address']],
            ['::1', null],
            ['256.256.256.256', ['Invalid IP address']],
            ['0.0.0.0', null],
            ['-0.0.0.0', ['Invalid IP address']],
            ['2001:db8:3333:4444:5555:6666:7777:8888', null],
            ['2001:db83333:4444:5555:6666:7777:8888', ['Invalid IP address']],
        ];
    }

    #[\Override]
    public function getConvertToDbValueCases(): array
    {
        return [
            ...parent::getConvertToDbValueCases(),
            ['127.0.0.1', '127.0.0.1'],
            ['::1', '::1'],
            ['2001:db8:3333:4444:5555:6666:7777:8888', '2001:db8:3333:4444:5555:6666:7777:8888'],
            ['2001:db83333:4444:5555:6666:7777:8888', '2001:db83333:4444:5555:6666:7777:8888'],
        ];
    }

    #[\Override]
    public function getDenormalizationCases(): array
    {
        return [
            ...parent::getDenormalizationCases(),
            ['127.0.0.1', '127.0.0.1'],
            ['::1', '::1'],
            ['2001:db8:3333:4444:5555:6666:7777:8888', '2001:db8:3333:4444:5555:6666:7777:8888'],
            ['2001:db83333:4444:5555:6666:7777:8888', '2001:db83333:4444:5555:6666:7777:8888'],
        ];
    }
}
