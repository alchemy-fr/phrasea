<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Alchemy\NotifierBundle\Notification\NotificationUrlGenerator;
use Alchemy\NotifierBundle\Topic\BuiltInTopic;
use Alchemy\NotifierBundle\Twig\NotificationUrlExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The `admin:message` templates ship with the bundle: an application gets the
 * built-in topic without declaring anything.
 */
final class BuiltInTemplatesTest extends TestCase
{
    private const array PARAMS = [
        'subject' => 'Maintenance',
        'body' => '<p>Tonight</p>',
        'url' => '/assets/42',
    ];

    public function testItFallsBackToTheBundleTemplates(): void
    {
        $renderer = $this->renderer();

        self::assertTrue($renderer->hasTemplate(BuiltInTopic::ADMIN_MESSAGE, ChannelType::Email));
        self::assertTrue($renderer->hasTemplate(BuiltInTopic::ADMIN_MESSAGE, ChannelType::InApp));
        self::assertTrue($renderer->hasTemplate(BuiltInTopic::ADMIN_MESSAGE, ChannelType::Sms));
        self::assertFalse($renderer->hasTemplate('not:declared', ChannelType::Email));
    }

    public function testEmailRendersTheContentAndTheLink(): void
    {
        $content = $this->renderer()->render(BuiltInTopic::ADMIN_MESSAGE, ChannelType::Email, self::PARAMS);

        self::assertNotNull($content);
        self::assertSame('Maintenance', $content->subject);
        self::assertStringContainsString('<p>Tonight</p>', $content->body);
        self::assertStringContainsString('https://client.example.com/notification-uri?uri=%2Fassets%2F42', $content->body);
    }

    public function testInAppExposesTheRawUri(): void
    {
        $content = $this->renderer()->render(BuiltInTopic::ADMIN_MESSAGE, ChannelType::InApp, self::PARAMS);

        self::assertNotNull($content);
        self::assertSame('Maintenance', $content->subject);
        self::assertSame('<p>Tonight</p>', $content->body);
        self::assertSame('/assets/42', $content->uri);
    }

    public function testSmsStripsTheMarkup(): void
    {
        $content = $this->renderer()->render(BuiltInTopic::ADMIN_MESSAGE, ChannelType::Sms, self::PARAMS);

        self::assertNotNull($content);
        self::assertSame("Maintenance\nTonight", $content->body);
    }

    public function testMissingParamsRenderEmpty(): void
    {
        $content = $this->renderer()->render(BuiltInTopic::ADMIN_MESSAGE, ChannelType::Email, []);

        self::assertNotNull($content);
        self::assertSame('', $content->subject);
        self::assertSame('', $content->body);
    }

    private function renderer(): NotificationRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'AlchemyNotifier');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new NotificationUrlExtension(new NotificationUrlGenerator('https://client.example.com')));

        // No app namespace registered: only the bundle templates can match
        return new NotificationRenderer($twig, '@notifications');
    }
}
