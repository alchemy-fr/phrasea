<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Delivery;

use Alchemy\NotifierBundle\Channel\ChannelInterface;
use Alchemy\NotifierBundle\Channel\ChannelRegistry;
use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Delivery\NotificationDeliverer;
use Alchemy\NotifierBundle\Digest\DigestBuffer;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Manager\PreferenceManager;
use Alchemy\NotifierBundle\Manager\SubscriberManager;
use Alchemy\NotifierBundle\Repository\NotificationPreferenceRepository;
use Alchemy\NotifierBundle\Topic\DigestConfig;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationDelivererDigestTest extends TestCase
{
    private const string TOPIC = 'discussion:new_comment';

    /** @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> */
    private array $channelSends = [];

    protected function setUp(): void
    {
        $this->channelSends = [];
    }

    public function testADigestedChannelBuffersInsteadOfSending(): void
    {
        $subscriber = $this->subscriber();
        $params = ['object' => 'Asset A', 'objectId' => 'obj-1'];

        $buffer = $this->createMock(DigestBuffer::class);
        $buffer->expects(self::once())->method('add')
            ->with($subscriber, self::TOPIC, ChannelType::Email, $params, self::isInstanceOf(DigestConfig::class));

        $this->deliverer($buffer, digest: true)->deliverTo($subscriber, self::TOPIC, $params);

        // The email went to the buffer, in-app was delivered immediately
        self::assertSame([ChannelType::InApp->value], array_column($this->channelSends, 0));
    }

    public function testATopicWithoutDigestDeliversDirectly(): void
    {
        $buffer = $this->createMock(DigestBuffer::class);
        $buffer->expects(self::never())->method('add');

        $this->deliverer($buffer, digest: false)->deliverTo($this->subscriber(), self::TOPIC);

        self::assertSame([ChannelType::Email->value, ChannelType::InApp->value], array_column($this->channelSends, 0));
    }

    public function testWithoutABufferTheDigestedChannelStillDelivers(): void
    {
        $this->deliverer(buffer: null, digest: true)->deliverTo($this->subscriber(), self::TOPIC);

        self::assertSame([ChannelType::Email->value, ChannelType::InApp->value], array_column($this->channelSends, 0));
    }

    public function testAnUnsupportedSubscriberIsNotBuffered(): void
    {
        $buffer = $this->createMock(DigestBuffer::class);
        $buffer->expects(self::never())->method('add');

        // supports() is false for every channel: nothing delivered, nothing buffered
        $this->deliverer($buffer, digest: true, supports: false)->deliverTo($this->subscriber(), self::TOPIC);

        self::assertSame([], $this->channelSends);
    }

    public function testABufferFailureDoesNotAbortTheOtherChannels(): void
    {
        $buffer = $this->createMock(DigestBuffer::class);
        $buffer->method('add')->willThrowException(new \RuntimeException('db gone'));

        $this->deliverer($buffer, digest: true)->deliverTo($this->subscriber(), self::TOPIC);

        self::assertSame([ChannelType::InApp->value], array_column($this->channelSends, 0));
    }

    private function subscriber(): Subscriber
    {
        $subscriber = new Subscriber('user-1');
        $subscriber->setEmail('user@example.com');

        return $subscriber;
    }

    private function deliverer(?DigestBuffer $buffer, bool $digest, bool $supports = true): NotificationDeliverer
    {
        $topicRegistry = new TopicRegistry([
            self::TOPIC => [
                'channels' => ['email', 'in_app'],
                'importance' => 'normal',
                'user_configurable' => true,
                'digest' => $digest ? [
                    'inactivity_delay' => 600,
                    'max_delay' => 3600,
                    'channels' => ['email'],
                    'group_by' => 'objectId',
                ] : null,
            ],
        ]);

        $preferenceRepository = $this->createMock(NotificationPreferenceRepository::class);
        $preferenceRepository->method('findOneForChannel')->willReturn(null);
        $preferenceManager = new PreferenceManager($this->createMock(EntityManagerInterface::class), $preferenceRepository, $topicRegistry);

        return new NotificationDeliverer(
            $this->createMock(SubscriberManager::class),
            $preferenceManager,
            new ChannelRegistry([
                $this->channel(ChannelType::Email, $supports),
                $this->channel(ChannelType::InApp, $supports),
            ]),
            $topicRegistry,
            null,
            $buffer,
        );
    }

    private function channel(ChannelType $type, bool $supports): ChannelInterface
    {
        $sends = &$this->channelSends;

        return new class($type, $supports, $sends) implements ChannelInterface {
            /** @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> */
            private array $sends;

            /**
             * @param array<int, array{0: string, 1: string, 2: array<string, mixed>}> $sends
             */
            public function __construct(
                private readonly ChannelType $type,
                private readonly bool $supports,
                array &$sends,
            ) {
                $this->sends = &$sends;
            }

            public function getType(): ChannelType
            {
                return $this->type;
            }

            public function supports(Subscriber $subscriber): bool
            {
                return $this->supports;
            }

            public function send(Subscriber $subscriber, string $topic, array $context = [], array $options = []): void
            {
                $this->sends[] = [$this->type->value, $topic, $context];
            }
        };
    }
}
