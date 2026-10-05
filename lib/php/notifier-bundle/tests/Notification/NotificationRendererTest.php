<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader as TranslationArrayLoader;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\Translator;
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

    public function testRendersUriBlock(): void
    {
        $renderer = $this->renderer([
            '@notifications/asset/comment/in_app.html.twig' => '{% block body %}New comment{% endblock %}{% block uri %}/assets/{{ assetId }}{% endblock %}',
        ]);

        $rendered = $renderer->render('asset.comment', ChannelType::InApp, ['assetId' => '42']);

        self::assertNotNull($rendered);
        self::assertSame('/assets/42', $rendered->uri);
    }

    public function testUriIsNullWhenNoUriBlock(): void
    {
        $renderer = $this->renderer([
            '@notifications/asset/comment/in_app.html.twig' => '{% block body %}New comment{% endblock %}',
        ]);

        $rendered = $renderer->render('asset.comment', ChannelType::InApp);

        self::assertNotNull($rendered);
        self::assertNull($rendered->uri);
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

    public function testRendersInTheGivenLocale(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new TranslationArrayLoader());
        $translator->addResource('array', ['greeting' => 'New comment'], 'en', 'notifications');
        $translator->addResource('array', ['greeting' => 'Nouveau commentaire'], 'fr', 'notifications');

        $twig = new Environment(new ArrayLoader([
            '@notifications/asset/comment/email.html.twig' => "{% block subject %}{{ 'greeting'|trans({}, 'notifications') }}{% endblock %}",
        ]));
        $twig->addExtension(new TranslationExtension($translator));

        $renderer = new NotificationRenderer($twig, '@notifications', new LocaleSwitcher('en', [$translator]));

        self::assertSame('Nouveau commentaire', $renderer->render('asset.comment', ChannelType::Email, [], 'fr')?->subject);
        self::assertSame('New comment', $renderer->render('asset.comment', ChannelType::Email, [], 'en')?->subject);
        // No locale passed: falls back to the switcher's default locale.
        self::assertSame('New comment', $renderer->render('asset.comment', ChannelType::Email)?->subject);
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
