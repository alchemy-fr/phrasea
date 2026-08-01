<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Twig\Environment;

/**
 * Renders application-provided Twig templates for a (topic, channel) pair.
 *
 * Templates are resolved under the configured namespace following the
 * convention: {namespace}/{topic-with-slashes}/{channel}.{ext}
 * e.g. `@notifications/asset/comment/email.html.twig`.
 *
 * A template may define a `subject` block, a `body` block and a `uri` block
 * (the main click-through target); when no `body` block is present the whole
 * template output is used as the body.
 */
final readonly class NotificationRenderer
{
    private const array CHANNEL_TEMPLATES = [
        ChannelType::Email->value => 'email.html.twig',
        ChannelType::Sms->value => 'sms.txt.twig',
        ChannelType::InApp->value => 'in_app.html.twig',
    ];

    public function __construct(
        private Environment $twig,
        private string $templateNamespace = '@notifications',
    ) {
    }

    public function hasTemplate(string $topic, ChannelType $channel): bool
    {
        return $this->twig->getLoader()->exists($this->templateName($topic, $channel));
    }

    public function render(string $topic, ChannelType $channel, array $context = []): ?RenderedContent
    {
        $name = $this->templateName($topic, $channel);
        if (!$this->twig->getLoader()->exists($name)) {
            return null;
        }

        $template = $this->twig->load($name);

        $subject = $template->hasBlock('subject', $context)
            ? trim($template->renderBlock('subject', $context))
            : null;

        $body = $template->hasBlock('body', $context)
            ? $template->renderBlock('body', $context)
            : $template->render($context);

        $uri = $template->hasBlock('uri', $context)
            ? trim($template->renderBlock('uri', $context))
            : null;

        return new RenderedContent($subject, trim($body), '' !== $uri ? $uri : null);
    }

    private function templateName(string $topic, ChannelType $channel): string
    {
        $topicPath = str_replace(['.', ':'], '/', $topic);

        return sprintf('%s/%s/%s', $this->templateNamespace, $topicPath, self::CHANNEL_TEMPLATES[$channel->value]);
    }
}
