<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Email;

use Alchemy\NotifierBundle\Controller\EmailLogoController;
use Alchemy\NotifierBundle\Notification\NotificationUrlGenerator;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Wraps the body rendered for a topic into the common email layout: a header
 * carrying the service logo (linked, never attached), the content card with
 * an optional call-to-action button (the `uri` and `action` blocks of the
 * topic template) and a footer.
 * A plain text alternative is built from the same content.
 *
 * The layouts can be overridden by the application as any bundle template
 * (`templates/bundles/AlchemyNotifierBundle/email/layout.html.twig`).
 */
final readonly class EmailLayoutRenderer
{
    public const string HTML_LAYOUT = '@AlchemyNotifier/email/layout.html.twig';
    public const string TEXT_LAYOUT = '@AlchemyNotifier/email/layout.txt.twig';

    private const int PREHEADER_LENGTH = 140;

    public function __construct(
        private Environment $twig,
        private NotificationUrlGenerator $notificationUrlGenerator,
        private EmailLogoResolver $logoResolver,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'alchemy_notifier.email.app_name')]
        private string $appName = 'Phrasea',
        #[Autowire(param: 'alchemy_notifier.email.public_url')]
        private string $publicUrl = '',
        private ?LocaleSwitcher $localeSwitcher = null,
    ) {
    }

    public function apply(Email $email, RenderedContent $content, ?string $locale = null): void
    {
        $logo = $this->logoResolver->resolve();

        $text = self::toText($content->body);
        $preheader = trim(preg_replace('#\s+#', ' ', $text));
        if (mb_strlen($preheader) > self::PREHEADER_LENGTH) {
            $preheader = rtrim(mb_substr($preheader, 0, self::PREHEADER_LENGTH - 1)).'…';
        }

        $context = [
            'locale' => $locale,
            'app_name' => $this->appName,
            'logo' => [
                'src' => $logo->remoteUrl ?? $this->getLogoUrl($logo),
                'width' => $logo->width,
                'height' => $logo->height,
                'custom' => $logo->custom,
            ],
            'subject' => $content->subject,
            'body' => $content->body,
            'text_body' => $text,
            'preheader' => $preheader,
            'action_url' => $this->notificationUrlGenerator->generate($content->uri),
            'action_label' => $content->action,
        ];

        $render = function () use ($email, $context): void {
            $email->html($this->twig->render(self::HTML_LAYOUT, $context));
            $email->text(trim($this->twig->render(self::TEXT_LAYOUT, $context))."\n");
        };

        if (null !== $locale && '' !== $locale && null !== $this->localeSwitcher) {
            $this->localeSwitcher->runWithLocale($locale, $render);
        } else {
            $render();
        }
    }

    /**
     * Topic bodies are small HTML fragments (paragraphs, links, lists): links
     * keep their target next to their label, block ends become line breaks.
     */
    public static function toText(string $html): string
    {
        $text = preg_replace_callback(
            '#<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $label = trim(strip_tags($m[3]));
                $href = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '' === $label || $label === $href ? $href : sprintf('%s (%s)', $label, $href);
            },
            $html,
        );
        $text = preg_replace('#\s*<li\b[^>]*>\s*#i', "\n- ", $text);
        $text = preg_replace('#\s*</li>#i', '', $text);
        $text = preg_replace('#\s*(?:<br\s*/?>|</tr>)\s*#i', "\n", $text);
        $text = preg_replace('#\s*</(?:p|div|h[1-6]|ul|ol|pre|table)>\s*#i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_map(static fn (string $line): string => trim(preg_replace('#[ \t]+#', ' ', $line)), explode("\n", $text));

        return trim(preg_replace("#\n{3,}#", "\n\n", implode("\n", $lines)));
    }

    /**
     * Workers have no request to build absolute URLs from: they are based on
     * the configured public URL of the application.
     */
    private function getLogoUrl(EmailLogo $logo): string
    {
        $parameters = ['v' => $logo->getVersion()];

        if ('' === $this->publicUrl) {
            return $this->urlGenerator->generate(EmailLogoController::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($this->publicUrl, '/').$this->urlGenerator->generate(EmailLogoController::ROUTE, $parameters);
    }
}
