<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Controller;

use Alchemy\NotifierBundle\Controller\EmailLogoController;
use Alchemy\NotifierBundle\Email\EmailLogoResolver;
use Alchemy\NotifierBundle\Tests\Email\EmailLayoutRendererTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class EmailLogoControllerTest extends TestCase
{
    public function testServesTheDefaultLogo(): void
    {
        $response = (new EmailLogoController(new EmailLogoResolver()))(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertSame(file_get_contents(__DIR__.'/../../assets/email/phrasea-logo.png'), $response->getContent());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    public function testServesADecodedDataUri(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
        $resolver = new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('data:image/svg+xml;base64,'.base64_encode($svg))]);

        $response = (new EmailLogoController($resolver))(new Request());

        self::assertSame($svg, $response->getContent());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }

    public function testTheCurrentVersionIsCachedForever(): void
    {
        $resolver = new EmailLogoResolver();

        $response = (new EmailLogoController($resolver))(new Request(['v' => $resolver->resolve()->getVersion()]));

        self::assertSame('31536000', $response->headers->getCacheControlDirective('max-age'));
        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));
    }

    public function testAStaleVersionIsNotCachedForever(): void
    {
        $response = (new EmailLogoController(new EmailLogoResolver()))(new Request(['v' => 'old']));

        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        self::assertFalse($response->headers->hasCacheControlDirective('immutable'));
    }

    public function testRedirectsToARemoteLogo(): void
    {
        $resolver = new EmailLogoResolver([EmailLayoutRendererTest::logoProvider('https://cdn.example.com/logo.png')]);

        $response = (new EmailLogoController($resolver))(new Request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://cdn.example.com/logo.png', $response->getTargetUrl());
    }
}
