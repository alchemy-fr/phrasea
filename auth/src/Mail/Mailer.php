<?php

declare(strict_types=1);

namespace App\Mail;

use Arthem\Bundle\RabbitBundle\Log\LoggableTrait;
use Psr\Log\LoggerAwareInterface;
use Swift_Mailer;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class Mailer implements LoggerAwareInterface
{
    use LoggableTrait;

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @var string
     */
    private $from;

    /**
     * @var EngineInterface
     */
    private $templating;

    public function __construct(EngineInterface $templating, Swift_Mailer $mailer, string $from)
    {
        $this->mailer = $mailer;
        $this->from = $from;
        $this->templating = $templating;
    }

    public function send(string $to, string $subject, string $template, array $parameters = []): void
    {
        $message = (new \Swift_Message(
            $subject,
            $this->renderView(
                $template,
                $parameters
            ),
            'text/html'
        ))
            ->setFrom($this->from)
            ->setTo($to);

        $this->logger->info(sprintf('Send mail "%s" to "%s"', $template, $to));
        $this->mailer->send($message);
    }

    private function renderView(string $template, array $parameters = []): string
    {
        return $this->templating->render($template, $parameters);
    }
}
