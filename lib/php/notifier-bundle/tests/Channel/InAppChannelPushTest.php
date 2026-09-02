<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Channel;

use Alchemy\CoreBundle\Message\PusherMessage;
use Alchemy\CoreBundle\Pusher\PusherManager;
use Alchemy\NotifierBundle\Channel\InAppChannel;
use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Pusher\Pusher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class InAppChannelPushTest extends TestCase
{
    public function testPushCarriesRenderedSubjectContentAndUri(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';
        $subscriber = new Subscriber($userId);

        // The pushed payload is dispatched (non-direct) as a PusherMessage on
        // the bus; capture it there to inspect what would be sent to Soketi.
        $captured = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            function (object $message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            }
        );

        $pusherManager = new PusherManager(
            new Pusher('key', 'secret', 'app-id'),
            $bus,
        );

        $renderer = new NotificationRenderer(new Environment(new ArrayLoader([
            '@notifications/asset/comment/in_app.html.twig' => '{% block subject %}New comment on {{ title }}{% endblock %}'
                .'{% block body %}<p>{{ author }} commented</p>{% endblock %}'
                .'{% block uri %}/assets/{{ assetId }}{% endblock %}',
        ])));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $channel = new InAppChannel($em, $pusherManager, 'private-user-', 'notification', $renderer);

        $channel->send($subscriber, 'asset.comment', [
            'title' => 'My asset',
            'author' => 'Jane',
            'assetId' => '42',
            // A `url` is present but the template's `uri` block takes precedence.
            'url' => '/ignored',
        ]);

        self::assertInstanceOf(PusherMessage::class, $captured);
        self::assertSame('private-user-'.$userId, $captured->getChannel());
        self::assertSame('notification', $captured->getEvent());

        $payload = $captured->getPayload();
        self::assertSame('New comment on My asset', $payload['subject']);
        self::assertSame('<p>Jane commented</p>', $payload['content']);
        self::assertSame('/assets/42', $payload['data']['uri']);
        self::assertNotEmpty($payload['id']);
    }

    public function testPushFallsBackToPayloadUrlWhenTemplateHasNoUriBlock(): void
    {
        $subscriber = new Subscriber('22222222-2222-2222-2222-222222222222');

        $captured = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            function (object $message) use (&$captured): Envelope {
                $captured = $message;

                return new Envelope($message);
            }
        );

        $pusherManager = new PusherManager(new Pusher('key', 'secret', 'app-id'), $bus);

        $renderer = new NotificationRenderer(new Environment(new ArrayLoader([
            '@notifications/asset/comment/in_app.html.twig' => '{% block body %}Hello{% endblock %}',
        ])));

        $em = $this->createMock(EntityManagerInterface::class);

        $channel = new InAppChannel($em, $pusherManager, 'private-user-', 'notification', $renderer);

        $channel->send($subscriber, 'asset.comment', ['url' => '/assets/7']);

        self::assertInstanceOf(PusherMessage::class, $captured);
        $payload = $captured->getPayload();
        self::assertSame('Hello', $payload['content']);
        self::assertNull($payload['subject']);
        self::assertSame('/assets/7', $payload['data']['uri']);
    }

    public function testDoesNotPushWithoutPusherManager(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        // No PusherManager: the notification is persisted but nothing is pushed.
        $channel = new InAppChannel($em);

        $channel->send(new Subscriber('33333333-3333-3333-3333-333333333333'), 'asset.comment', []);

        $this->addToAssertionCount(1);
    }
}
