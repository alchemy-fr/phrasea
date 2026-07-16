<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Channel;

use Alchemy\NotifierBundle\Channel\ChannelInterface;
use Alchemy\NotifierBundle\Channel\ChannelRegistry;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use PHPUnit\Framework\TestCase;

final class ChannelRegistryTest extends TestCase
{
    public function testGetReturnsChannelByType(): void
    {
        $email = $this->channel(ChannelType::Email);
        $inApp = $this->channel(ChannelType::InApp);

        $registry = new ChannelRegistry([$email, $inApp]);

        self::assertTrue($registry->has(ChannelType::Email));
        self::assertTrue($registry->has(ChannelType::InApp));
        self::assertFalse($registry->has(ChannelType::Sms));
        self::assertSame($email, $registry->get(ChannelType::Email));
    }

    public function testGetUnknownChannelThrows(): void
    {
        $registry = new ChannelRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $registry->get(ChannelType::Sms);
    }

    private function channel(ChannelType $type): ChannelInterface
    {
        return new class($type) implements ChannelInterface {
            public function __construct(private readonly ChannelType $type)
            {
            }

            public function getType(): ChannelType
            {
                return $this->type;
            }

            public function supports(Subscriber $subscriber): bool
            {
                return true;
            }

            public function send(Subscriber $subscriber, string $topic, RenderedContent $content, array $context = [], array $options = []): void
            {
            }
        };
    }
}
