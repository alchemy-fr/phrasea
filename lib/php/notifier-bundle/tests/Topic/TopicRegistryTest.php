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
}
