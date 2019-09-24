<?php

declare(strict_types=1);

namespace App\Tests;

use App\Mail\Mailer;
use App\Mail\RenderingContext;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerTest extends WebTestCase
{
    public function testMailerSendEmailOK(): void
    {
        $renderTwig = function ($tpl): string {
            $contents = [
                'tpl_subject.html.twig' => 'Subject content',
                'tpl.html.twig' => 'Body content',
            ];

            return $contents[$tpl];
        };

        /** @var Environment|MockObject $templating */
        $templating = $this->createMock(Environment::class);
        $templating
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback($renderTwig);

        $email = new Email();
        $email
            ->to('test@test.fr')
            ->subject('Subject content')
            ->from('noreply@test')
            ->html('Body content');
            ;

        /** @var MailerInterface|MockObject $symfonyMailer */
        $symfonyMailer = $this->createMock(MailerInterface::class);
        $symfonyMailer->expects($this->once())
            ->method('send')
            ->with($this->equalTo($email))
        ;

        /** @var RenderingContext|MockObject $renderingContext */
        $renderingContext = $this->createMock(RenderingContext::class);

        $mailer = new Mailer(
            $templating,
            $symfonyMailer,
            $renderingContext,
            'noreply@test'
        );
        $mailer->setLogger(new NullLogger());

        $mailer->send('test@test.fr', 'tpl', []);
    }
}
