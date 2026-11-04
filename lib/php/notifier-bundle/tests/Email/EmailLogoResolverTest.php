<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Email;

use Alchemy\NotifierBundle\Email\EmailLogoResolver;
use PHPUnit\Framework\TestCase;

final class EmailLogoResolverTest extends TestCase
{
    private const string DEFAULT_LOGO = __DIR__.'/../../assets/email/phrasea-logo.png';

    public function testDefaultsToThePhraseaLogo(): void
    {
        $logo = (new EmailLogoResolver())->resolve();

        self::assertFalse($logo->custom);
        self::assertNull($logo->remoteUrl);
        self::assertSame(file_get_contents(self::DEFAULT_LOGO), $logo->content);
        self::assertSame('image/png', $logo->mimeType);
        self::assertSame([40, 40], [$logo->width, $logo->height]);
    }

    public function testDecodesADataUri(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        $logo = (new EmailLogoResolver([
            EmailLayoutRendererTest::logoProvider('data:image/svg+xml;base64,'.base64_encode($svg)),
        ]))->resolve();

        self::assertTrue($logo->custom);
        self::assertSame($svg, $logo->content);
        self::assertSame('image/svg+xml', $logo->mimeType);
        // An SVG cannot be measured
        self::assertSame([null, null], [$logo->width, $logo->height]);
    }

    public function testTheFirstValidSourceWins(): void
    {
        $logo = (new EmailLogoResolver([
            EmailLayoutRendererTest::logoProvider(null),
            EmailLayoutRendererTest::logoProvider('javascript:alert(1)'),
            EmailLayoutRendererTest::logoProvider('data:image/png;base64,!!!'),
            EmailLayoutRendererTest::logoProvider('https://cdn.example.com/logo.png'),
            EmailLayoutRendererTest::logoProvider('https://cdn.example.com/other.png'),
        ]))->resolve();

        self::assertTrue($logo->custom);
        self::assertSame('https://cdn.example.com/logo.png', $logo->remoteUrl);
        self::assertNull($logo->content);
    }

    public function testFallsBackToTheDefaultLogoOnInvalidSources(): void
    {
        $logo = (new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('javascript:alert(1)')]))->resolve();

        self::assertFalse($logo->custom);
    }

    public function testTheVersionFollowsTheLogo(): void
    {
        $a = (new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('https://cdn.example.com/a.png')]))->resolve();
        $b = (new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('https://cdn.example.com/b.png')]))->resolve();

        self::assertNotSame($a->getVersion(), $b->getVersion());
        self::assertSame($a->getVersion(), (new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('https://cdn.example.com/a.png')]))->resolve()->getVersion());
    }
}
