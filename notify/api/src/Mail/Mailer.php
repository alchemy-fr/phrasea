<?php

declare(strict_types=1);

namespace App\Mail;

use Arthem\Bundle\RabbitBundle\Log\LoggableTrait;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;

class Mailer implements LoggerAwareInterface
{
    use LoggableTrait;

    private MailerInterface $mailer;
    private string $from;
    private Environment $templating;
    private RenderingContext $renderingContext;

    public function __construct(
        Environment $templating,
        MailerInterface $mailer,
        RenderingContext $renderingContext,
        string $from
    ) {
        $this->mailer = $mailer;
        $this->from = $from;
        $this->templating = $templating;
        $this->renderingContext = $renderingContext;
    }

    public function send(string $to, string $template, array $parameters, ?string $locale = null): void
    {
        $this->renderingContext->setLocale($locale ?? 'en');

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($this->renderSubject($template, $parameters))
            ->html($this->renderView($template, $parameters));

        $this->logger->info(sprintf('Send mail "%s" to "%s" in "%s"', $template, $to, $locale));
        $this->mailer->send($email);
    }

    private function renderView(string $template, array $parameters): string
    {
        return $this->renderFile($template, $parameters);
    }

    private function renderSubject(string $template, array $parameters): string
    {
        return $this->renderFile($template.'_subject', $parameters);
    }

    private function renderFile(string $file, array $parameters): string
    {
        return $this->templating->render($file.'.html.twig', $parameters);
    }

    public function validateParameters(string $template, array $parameters): void
    {
        try {
            $this->renderSubject($template, $parameters);
            $this->renderView($template, $parameters);
        } catch (LoaderError $e) {
            throw new BadRequestHttpException(sprintf('Undefined template "%s"', $template));
        } catch (RuntimeError $e) {
            if (1 === preg_match('#^Variable "([^"]+)" does not exist.$#', $e->getMessage(), $regs)) {
                throw new BadRequestHttpException(sprintf('Missing parameter "%s"', $regs[1]));
            }

            throw $e;
        }
    }
}
