<?php

declare(strict_types=1);

namespace App\Mail;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;

class Mailer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private readonly Environment $templating, private readonly MailerInterface $mailer, private readonly RenderingContext $renderingContext, private readonly string $from)
    {
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
        } catch (LoaderError) {
            throw new BadRequestHttpException(sprintf('Undefined template "%s"', $template));
        } catch (RuntimeError $e) {
            if (1 === preg_match('#^Variable "([^"]+)" does not exist.$#', $e->getMessage(), $regs)) {
                throw new BadRequestHttpException(sprintf('Missing parameter "%s"', $regs[1]));
            }

            throw $e;
        }
    }
}
