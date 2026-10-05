<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Model;

use Alchemy\NotifierBundle\Model\NotifySelectorDto;
use Alchemy\NotifierBundle\Model\TopicDto;
use PHPUnit\Framework\TestCase;

final class NotifySelectorDtoTest extends TestCase
{
    public function testEventOnlySelectorIsValid(): void
    {
        $selector = new NotifySelectorDto(event: 'asset_added', objectType: 'collection', objectId: '42');

        self::assertSame('asset_added', $selector->event);
        self::assertSame('collection', $selector->objectType);
        self::assertSame('42', $selector->objectId);
        self::assertSame([], $selector->userIds);
        self::assertNull($selector->topic);
    }

    public function testUserOnlySelectorIsValid(): void
    {
        $selector = new NotifySelectorDto(userIds: ['u1'], topic: new TopicDto('asset_added', ['x' => 1]));

        self::assertNull($selector->event);
        self::assertSame(['u1'], $selector->userIds);
        self::assertSame('asset_added', $selector->topic->topic);
    }

    public function testSelectorWithoutEventNorUsersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new NotifySelectorDto(objectType: 'collection', objectId: '42');
    }
}
