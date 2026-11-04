<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class NotificationRendererDigestTest extends TestCase
{
    public function testRendersTheDigestTemplate(): void
    {
        $renderer = $this->renderer([
            '@notifications/discussion/new_comment/email_digest.html.twig' => '{% block subject %}{{ count }} new comments{% endblock %}{% block body %}<p>{{ events|length }} shown</p>{% endblock %}',
        ]);

        $rendered = $renderer->renderDigest('discussion:new_comment', ChannelType::Email, [
            'count' => 5,
            'events' => [1, 2, 3],
        ]);

        self::assertNotNull($rendered);
        self::assertSame('5 new comments', $rendered->subject);
        self::assertSame('<p>3 shown</p>', $rendered->body);
    }

    public function testMissingDigestTemplateReturnsNull(): void
    {
        $renderer = $this->renderer([
            '@notifications/discussion/new_comment/email.html.twig' => '{% block body %}single{% endblock %}',
        ]);

        self::assertNull($renderer->renderDigest('discussion:new_comment', ChannelType::Email));
        self::assertFalse($renderer->hasDigestTemplate('discussion:new_comment', ChannelType::Email));
        // The regular template is still there
        self::assertTrue($renderer->hasTemplate('discussion:new_comment', ChannelType::Email));
    }

    public function testChannelsWithoutDigestSupportReturnNull(): void
    {
        $renderer = $this->renderer([
            '@notifications/discussion/new_comment/in_app.html.twig' => '{% block body %}x{% endblock %}',
        ]);

        self::assertNull($renderer->renderDigest('discussion:new_comment', ChannelType::InApp));
        self::assertFalse($renderer->hasDigestTemplate('discussion:new_comment', ChannelType::InApp));
    }

    public function testApplicationNamespaceOverridesTheBundleTemplate(): void
    {
        $twig = new Environment(new ArrayLoader([
            '@notifications/discussion/new_comment/email_digest.html.twig' => '{% block body %}app{% endblock %}',
            '@AlchemyNotifier/notifications/discussion/new_comment/email_digest.html.twig' => '{% block body %}bundle{% endblock %}',
        ]));
        $renderer = new NotificationRenderer($twig, '@notifications', null, '@AlchemyNotifier/notifications');

        self::assertSame('app', $renderer->renderDigest('discussion:new_comment', ChannelType::Email)?->body);
    }

    /**
     * @param array<string, string> $templates
     */
    private function renderer(array $templates): NotificationRenderer
    {
        $twig = new Environment(new ArrayLoader($templates));

        return new NotificationRenderer($twig, '@notifications');
    }
}
