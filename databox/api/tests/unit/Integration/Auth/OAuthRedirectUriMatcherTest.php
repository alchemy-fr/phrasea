<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Auth;

use App\Integration\Auth\OAuthRedirectUriMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthRedirectUriMatcherTest extends TestCase
{
    private const string TARGET = 'https://api-databox.phrasea.test/integrations/0b8f2c4e-1111-4c6f-9d1a-123456789abc/code';

    #[DataProvider('getCases')]
    public function testIsAllowed(array $redirectUris, ?string $rootUrl, bool $expected): void
    {
        $this->assertSame($expected, OAuthRedirectUriMatcher::isAllowed(self::TARGET, $redirectUris, $rootUrl));
    }

    public static function getCases(): iterable
    {
        yield 'none' => [[], null, false];
        yield 'same URI' => [[self::TARGET], null, true];
        yield 'another integration' => [['https://api-databox.phrasea.test/integrations/another-id/code'], null, false];
        yield 'wildcard of the integrations' => [['https://api-databox.phrasea.test/integrations/*'], null, true];
        yield 'wildcard shorter than the integrations' => [['https://api-databox.phrasea.test/*'], null, true];
        yield 'wildcard of another path' => [['https://api-databox.phrasea.test/admin/*'], null, false];
        yield 'wildcard of another host' => [['https://api-expose.phrasea.test/*'], null, false];
        yield 'wildcard of a host prefix' => [['https://api-databox.phrasea.test.evil.com/*'], null, false];
        yield 'relative, resolved against the root URL' => [['/integrations/*'], 'https://api-databox.phrasea.test', true];
        yield 'relative, root URL with a trailing slash' => [['/*'], 'https://api-databox.phrasea.test/', true];
        yield 'relative, root URL of another host' => [['/*'], 'https://databox.phrasea.test', false];
        yield 'relative without root URL' => [['/*'], null, false];
        yield 'plus' => [['+'], 'https://api-databox.phrasea.test', false];
        yield 'minus' => [['-'], 'https://api-databox.phrasea.test', false];
        yield 'one of several' => [['https://databox.phrasea.test/*', 'https://api-databox.phrasea.test/*'], null, true];
    }

    public function testRedirectUriOfTheCreatedClients(): void
    {
        $matcher = new OAuthRedirectUriMatcher($this->createUrlGenerator('https://api-databox.phrasea.test:8443'));

        $this->assertSame('https://api-databox.phrasea.test:8443/integrations/*', $matcher->getClientRedirectUri());
        $this->assertSame('https://api-databox.phrasea.test:8443', $matcher->getClientRootUrl());
        $this->assertTrue(OAuthRedirectUriMatcher::isAllowed(
            $matcher->getRedirectUri('0b8f2c4e-1111-4c6f-9d1a-123456789abc'),
            [$matcher->getClientRedirectUri()],
        ));
    }

    public function testRedirectUriOfAnIntegration(): void
    {
        $matcher = new OAuthRedirectUriMatcher($this->createUrlGenerator('https://api-databox.phrasea.test'));

        $this->assertSame(self::TARGET, $matcher->getRedirectUri('0b8f2c4e-1111-4c6f-9d1a-123456789abc'));
        $this->assertSame(
            'https://api-databox.phrasea.test/integrations/00000000-0000-0000-0000-000000000000/code',
            $matcher->getRedirectUri(),
        );
    }

    private function createUrlGenerator(string $baseUrl): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(function (string $name, array $parameters, int $referenceType) use ($baseUrl): string {
                $this->assertSame('integration_auth_code', $name);
                $this->assertSame(UrlGeneratorInterface::ABSOLUTE_URL, $referenceType);

                return sprintf('%s/integrations/%s/code', $baseUrl, $parameters['integrationId']);
            });

        return $urlGenerator;
    }
}
