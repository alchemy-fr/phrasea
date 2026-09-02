<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Renders application-provided Twig templates for a (topic, channel) pair.
 *
 * Templates are resolved under the configured namespace following the
 * convention: {namespace}/{topic-with-slashes}/{channel}.{ext}
 * e.g. `@notifications/asset/comment/email.html.twig`. When the application
 * defines none, the bundle's own templates are used as a fallback (built-in
 * topics such as `admin:message`).
 *
 * A template may define a `subject` block, a `body` block and a `uri` block
 * (the main click-through target); when no `body` block is present the whole
 * template output is used as the body.
 *
 * Templates translate their literal strings through the Twig `trans` filter
 * (domain `notifications`). When a `$locale` is passed to {@see render()} the
 * whole rendering runs under that locale so the catalog is resolved in the
 * recipient's language.
 */
final readonly class NotificationRenderer
{
    /**
     * Namespace of the templates shipped by the bundle itself (built-in topics).
     */
    public const string BUILT_IN_NAMESPACE = '@AlchemyNotifier/notifications';

    private const array CHANNEL_TEMPLATES = [
        ChannelType::Email->value => 'email.html.twig',
        ChannelType::Sms->value => 'sms.txt.twig',
        ChannelType::InApp->value => 'in_app.html.twig',
    ];

    /**
     * Templates rendering a whole digest bucket (multiple buffered events) on
     * the channels that support digests.
     */
    private const array DIGEST_CHANNEL_TEMPLATES = [
        ChannelType::Email->value => 'email_digest.html.twig',
    ];

    /**
     * The LocaleSwitcher is optional: without symfony/translation installed the
     * renderer still works, it just cannot switch the active locale.
     */
    public function __construct(
        private Environment $twig,
        #[Autowire(param: 'alchemy_notifier.template_namespace')]
        private string $templateNamespace = '@notifications',
        private ?LocaleSwitcher $localeSwitcher = null,
        private string $fallbackNamespace = self::BUILT_IN_NAMESPACE,
    ) {
    }

    public function hasTemplate(string $topic, ChannelType $channel): bool
    {
        return null !== $this->resolveTemplateName($topic, self::CHANNEL_TEMPLATES[$channel->value]);
    }

    public function hasDigestTemplate(string $topic, ChannelType $channel): bool
    {
        $file = self::DIGEST_CHANNEL_TEMPLATES[$channel->value] ?? null;

        return null !== $file && null !== $this->resolveTemplateName($topic, $file);
    }

    public function render(string $topic, ChannelType $channel, array $context = [], ?string $locale = null): ?RenderedContent
    {
        return $this->renderFile($topic, self::CHANNEL_TEMPLATES[$channel->value], $context, $locale);
    }

    /**
     * Renders the digest template of the topic, with a context describing the
     * whole bucket (`events`, `count`, `byObject`, ...) instead of one event.
     */
    public function renderDigest(string $topic, ChannelType $channel, array $context = [], ?string $locale = null): ?RenderedContent
    {
        $file = self::DIGEST_CHANNEL_TEMPLATES[$channel->value] ?? null;
        if (null === $file) {
            return null;
        }

        return $this->renderFile($topic, $file, $context, $locale);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderFile(string $topic, string $file, array $context, ?string $locale): ?RenderedContent
    {
        $name = $this->resolveTemplateName($topic, $file);
        if (null === $name) {
            return null;
        }

        $render = fn (): RenderedContent => $this->doRender($name, $context);

        if (null !== $locale && '' !== $locale && null !== $this->localeSwitcher) {
            return $this->localeSwitcher->runWithLocale($locale, $render);
        }

        return $render();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function doRender(string $name, array $context): RenderedContent
    {
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

    /**
     * The application always wins: a template it defines under its own
     * namespace overrides the one shipped by the bundle.
     */
    private function resolveTemplateName(string $topic, string $file): ?string
    {
        $topicPath = str_replace(['.', ':'], '/', $topic);

        foreach (array_unique(array_filter([$this->templateNamespace, $this->fallbackNamespace])) as $namespace) {
            $name = sprintf('%s/%s/%s', $namespace, $topicPath, $file);
            if ($this->twig->getLoader()->exists($name)) {
                return $name;
            }
        }

        return null;
    }
}
