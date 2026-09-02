<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Topic;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Topic\TopicRegistry;
use PHPUnit\Framework\TestCase;

final class TopicRegistryTest extends TestCase
{
    public function testResolvesTopicDefinition(): void
    {
        $registry = new TopicRegistry([
            'asset.comment' => [
                'channels' => ['email', 'in_app'],
                'importance' => 'high',
                'user_configurable' => false,
            ],
        ]);

        self::assertTrue($registry->has('asset.comment'));
        self::assertFalse($registry->has('unknown'));

        $topic = $registry->get('asset.comment');
        self::assertSame('asset.comment', $topic->key);
        self::assertSame([ChannelType::Email, ChannelType::InApp], $topic->channels);
        self::assertSame('high', $topic->importance);
        self::assertFalse($topic->userConfigurable);
        self::assertArrayHasKey('asset.comment', $registry->all());
    }

    public function testUnknownTopicThrows(): void
    {
        $registry = new TopicRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('nope');
    }

    public function testTopicWithoutDigestHasNone(): void
    {
        $registry = new TopicRegistry([
            'asset.comment' => [
                'channels' => ['email'],
                'importance' => 'normal',
                'user_configurable' => true,
            ],
        ]);

        self::assertNull($registry->get('asset.comment')->digest);
    }

    public function testDigestConfigIsParsed(): void
    {
        $registry = new TopicRegistry([
            'asset.comment' => [
                'channels' => ['email', 'in_app'],
                'importance' => 'normal',
                'user_configurable' => true,
                'digest' => [
                    'inactivity_delay' => 120,
                    'max_delay' => 900,
                    'channels' => ['email'],
                    'group_by' => 'name',
                ],
            ],
        ]);

        $digest = $registry->get('asset.comment')->digest;
        self::assertNotNull($digest);
        self::assertSame(120, $digest->inactivityDelay);
        self::assertSame(900, $digest->maxDelay);
        self::assertSame([ChannelType::Email], $digest->channels);
        self::assertSame('name', $digest->groupBy);
        self::assertTrue($digest->applies(ChannelType::Email));
        self::assertFalse($digest->applies(ChannelType::InApp));
    }
}
