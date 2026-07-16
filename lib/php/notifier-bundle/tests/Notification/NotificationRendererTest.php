<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class NotificationRendererTest extends TestCase
{
    public function testRendersSubjectAndBodyBlocks(): void
    {
        $renderer = $this->renderer([
            '@notifications/asset/comment/email.html.twig' => '{% block subject %}New comment on {{ title }}{% endblock %}{% block body %}<p>{{ author }}</p>{% endblock %}',
        ]);

        $rendered = $renderer->render('asset.comment', ChannelType::Email, [
            'title' => 'My asset',
            'author' => 'Jane',
        ]);

        self::assertNotNull($rendered);
        self::assertSame('New comment on My asset', $rendered->subject);
        self::assertSame('<p>Jane</p>', $rendered->body);
    }

    public function testBodyOnlyTemplateHasNoSubject(): void
    {
        $renderer = $this->renderer([
            '@notifications/asset/comment/sms.txt.twig' => 'New comment from {{ author }}',
        ]);

        $rendered = $renderer->render('asset.comment', ChannelType::Sms, ['author' => 'Jane']);

        self::assertNotNull($rendered);
        self::assertNull($rendered->subject);
        self::assertSame('New comment from Jane', $rendered->body);
    }

    public function testMissingTemplateReturnsNull(): void
    {
        $renderer = $this->renderer([]);

        self::assertNull($renderer->render('asset.comment', ChannelType::Email));
        self::assertFalse($renderer->hasTemplate('asset.comment', ChannelType::Email));
    }

    public function testColonSeparatorInTopicMapsToPath(): void
    {
        $renderer = $this->renderer([
            '@notifications/asset/123/in_app.html.twig' => '{% block body %}ok{% endblock %}',
        ]);

        self::assertTrue($renderer->hasTemplate('asset:123', ChannelType::InApp));
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
