<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Email;

use Alchemy\NotifierBundle\Controller\EmailLogoController;
use Alchemy\NotifierBundle\Email\EmailLayoutRenderer;
use Alchemy\NotifierBundle\Email\EmailLogoProviderInterface;
use Alchemy\NotifierBundle\Email\EmailLogoResolver;
use Alchemy\NotifierBundle\Notification\NotificationUrlGenerator;
use Alchemy\NotifierBundle\Notification\RenderedContent;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class EmailLayoutRendererTest extends TestCase
{
    private const string DEFAULT_LOGO = __DIR__.'/../../assets/email/phrasea-logo.png';

    public function testWrapsTheBodyWithTheDefaultLogoAndTheAppName(): void
    {
        $email = $this->apply(new RenderedContent('New comment', '<p>Hi <strong>Jane</strong>,</p>'));

        $html = $email->getHtmlBody();
        self::assertStringContainsString('<p>Hi <strong>Jane</strong>,</p>', $html);
        self::assertStringContainsString('<h1', $html);
        self::assertStringContainsString('New comment', $html);
        self::assertStringContainsString('width="40" height="40"', $html);
        self::assertStringContainsString('>Databox</td>', $html);
        self::assertStringContainsString('email_layout.footer', $html);

        $version = (new EmailLogoResolver())->resolve()->getVersion();
        self::assertStringContainsString('src="https://api.example.com/notifications/email-logo?v='.$version.'"', $html);
    }

    public function testTheLogoIsNeverAttached(): void
    {
        $src = 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::DEFAULT_LOGO));

        self::assertSame([], $this->apply(new RenderedContent('Subject', '<p>Body</p>'))->getAttachments());
        self::assertSame([], $this->apply(new RenderedContent('Subject', '<p>Body</p>'), [self::logoProvider($src)])->getAttachments());
    }

    public function testLinksACustomLogoToTheLogoEndpoint(): void
    {
        $provider = self::logoProvider('data:image/png;base64,'.base64_encode((string) file_get_contents(self::DEFAULT_LOGO)));

        $html = $this->apply(new RenderedContent('Subject', '<p>Body</p>'), [$provider])->getHtmlBody();

        $version = (new EmailLogoResolver([$provider]))->resolve()->getVersion();
        self::assertStringContainsString('src="https://api.example.com/notifications/email-logo?v='.$version.'"', $html);
        // 180x180 fitted into the 200x48 header box
        self::assertStringContainsString('width="48" height="48"', $html);
        // A custom logo already carries the brand
        self::assertStringNotContainsString('>Databox</td>', $html);
    }

    public function testReferencesARemoteLogoDirectly(): void
    {
        $email = $this->apply(new RenderedContent('Subject', '<p>Body</p>'), [
            self::logoProvider('https://cdn.example.com/logo.png'),
        ]);

        self::assertStringContainsString('src="https://cdn.example.com/logo.png"', $email->getHtmlBody());
    }

    public function testFallsBackToTheRequestUrlWithoutPublicUrl(): void
    {
        $email = new Email();
        self::createRenderer(publicUrl: '')->apply($email, new RenderedContent('Subject', '<p>Body</p>'));

        self::assertStringContainsString('src="https://request.example.com/notifications/email-logo?v=', $email->getHtmlBody());
    }

    public function testRendersTheActionButton(): void
    {
        $email = $this->apply(new RenderedContent('Subject', '<p>Body</p>', '/assets/42', 'View the asset'));

        $href = 'https://client.example.com/notification-uri?uri=%2Fassets%2F42';
        self::assertStringContainsString('href="'.$href.'"', $email->getHtmlBody());
        self::assertStringContainsString('>View the asset</a>', $email->getHtmlBody());
        self::assertStringContainsString('View the asset: '.$href, $email->getTextBody());
    }

    public function testTheActionButtonHasADefaultLabel(): void
    {
        $email = $this->apply(new RenderedContent('Subject', '<p>Body</p>', 'https://download.example.com/x?a=1&b=2'));

        self::assertStringContainsString('href="https://download.example.com/x?a=1&amp;b=2"', $email->getHtmlBody());
        self::assertStringContainsString('>email_layout.action</a>', $email->getHtmlBody());
    }

    public function testNoActionButtonWithoutUri(): void
    {
        $email = $this->apply(new RenderedContent('Subject', '<p>Body</p>'));

        self::assertStringNotContainsString('email_layout.action', $email->getHtmlBody());
    }

    public function testEscapesTheSubject(): void
    {
        $email = $this->apply(new RenderedContent('<b>Tom & Jerry</b>', '<p>Body</p>'));

        self::assertStringContainsString('&lt;b&gt;Tom &amp; Jerry&lt;/b&gt;', $email->getHtmlBody());
    }

    public function testBuildsThePlainTextAlternative(): void
    {
        $email = $this->apply(new RenderedContent('Subject', '<p>Hi Jane,</p><p><strong>Bob</strong> commented on <a href="https://x.test/a?b=1&amp;c=2">My &amp; asset</a>.</p>'));

        self::assertSame(
            "Hi Jane,\n\nBob commented on My & asset (https://x.test/a?b=1&c=2).\n\n--\nemail_layout.footer\n",
            $email->getTextBody(),
        );
    }

    public function testToTextRendersLists(): void
    {
        self::assertSame(
            "3 assets added to Photos (https://x.test):\n\n- One — by Jane\n- Two\n\nAnd more.",
            EmailLayoutRenderer::toText("<p>3 assets added to <a href=\"https://x.test\"><em>Photos</em></a>:</p>\n<ul>\n    <li><strong>One</strong> — by Jane</li>\n    <li>Two</li>\n</ul>\n<p>And more.</p>"),
        );
    }

    /**
     * @param iterable<EmailLogoProviderInterface> $logoProviders
     */
    public static function createRenderer(iterable $logoProviders = [], string $publicUrl = 'https://api.example.com/'): EmailLayoutRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'AlchemyNotifier');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension());

        $routes = new RouteCollection();
        $routes->add(EmailLogoController::ROUTE, new Route('/notifications/email-logo'));

        return new EmailLayoutRenderer(
            $twig,
            new NotificationUrlGenerator('https://client.example.com'),
            new EmailLogoResolver($logoProviders),
            new UrlGenerator($routes, new RequestContext(host: 'request.example.com', scheme: 'https')),
            'Databox',
            $publicUrl,
        );
    }

    /**
     * @param iterable<EmailLogoProviderInterface> $logoProviders
     */
    private function apply(RenderedContent $content, iterable $logoProviders = []): Email
    {
        $email = new Email();
        self::createRenderer($logoProviders)->apply($email, $content, 'en');

        return $email;
    }

    public static function logoProvider(?string $src): EmailLogoProviderInterface
    {
        return new readonly class($src) implements EmailLogoProviderInterface {
            public function __construct(private ?string $src)
            {
            }

            public function getLogoSrc(): ?string
            {
                return $this->src;
            }
        };
    }
}
