<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Message;

use Alchemy\NotifierBundle\Channel\EmailChannel;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use Alchemy\NotifierBundle\Message\SendEmailNotificationHandler;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SendEmailNotificationHandlerTest extends TestCase
{
    public function testRendersEmailTemplateAndSendsToAddress(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(
            function (Email $email) use (&$sent): void {
                $sent = $email;
            }
        );

        $handler = $this->handler($mailer, [
            '@notifications/expose-download-link/email.html.twig' => '{% block subject %}Your download{% endblock %}{% block body %}<a href="{{ downloadUrl }}">dl</a> {{ recipient.locale }}{% endblock %}',
        ]);

        $handler(new SendEmailNotification('bob@example.com', 'expose-download-link', ['downloadUrl' => 'https://x'], 'fr'));

        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('bob@example.com', $sent->getTo()[0]->getAddress());
        self::assertSame('Your download', $sent->getSubject());
        self::assertStringContainsString('https://x', $sent->getHtmlBody());
        self::assertStringContainsString('fr', $sent->getHtmlBody());
    }

    public function testDoesNothingWhenNoTemplate(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $handler = $this->handler($mailer, []);

        $handler(new SendEmailNotification('bob@example.com', 'unknown-topic'));
    }

    /**
     * @param array<string, string> $templates
     */
    private function handler(MailerInterface $mailer, array $templates): SendEmailNotificationHandler
    {
        $renderer = new NotificationRenderer(new Environment(new ArrayLoader($templates)), '@notifications');

        return new SendEmailNotificationHandler($renderer, new EmailChannel($mailer));
    }
}
