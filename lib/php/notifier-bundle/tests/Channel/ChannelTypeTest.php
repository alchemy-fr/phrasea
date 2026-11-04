<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Channel;

use Alchemy\NotifierBundle\Channel\ChannelType;
use PHPUnit\Framework\TestCase;

final class ChannelTypeTest extends TestCase
{
    public function testValuesReturnsEveryCaseValue(): void
    {
        self::assertSame(['email', 'sms', 'in_app'], ChannelType::values());
    }

    public function testFromValue(): void
    {
        self::assertSame(ChannelType::InApp, ChannelType::from('in_app'));
        self::assertNull(ChannelType::tryFrom('carrier-pigeon'));
    }
}
