<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Channel;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Channel\InAppChannel;
use Alchemy\NotifierBundle\Entity\Notification;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class InAppChannelTest extends TestCase
{
    public function testStoresTopicAndPayloadWithoutRendering(): void
    {
        $subscriber = new Subscriber('11111111-1111-1111-1111-111111111111');

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(
            function (Notification $n) use (&$persisted): void {
                $persisted = $n;
            }
        );
        $em->expects(self::once())->method('flush');

        $channel = new InAppChannel($em);

        self::assertSame(ChannelType::InApp, $channel->getType());

        $channel->send($subscriber, 'asset_added', ['name' => 'Sunset.jpg', 'author' => 'Jane']);

        self::assertInstanceOf(Notification::class, $persisted);
        self::assertSame('asset_added', $persisted->getTopic());
        self::assertSame(['name' => 'Sunset.jpg', 'author' => 'Jane'], $persisted->getPayload());
        self::assertFalse($persisted->isRead());
    }
}
