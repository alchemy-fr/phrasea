<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Publication;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class PublicHttpCacheTest extends AbstractExposeTestCase
{
    #[DataProvider('getAcceptTypes')]
    public function testPublicPublicationIsCacheable(string $accept): void
    {
        $publication = $this->createPublication(['slug' => 'cached-pub']);
        $assetId = $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        foreach ([
            '/publications/'.$publication->getId(),
            '/publications/cached-pub',
            '/assets/'.$assetId,
        ] as $uri) {
            $response = $this->request(null, 'GET', $uri, [], [], [
                'HTTP_ACCEPT' => $accept,
                'HTTP_ORIGIN' => 'https://expose.example',
            ]);
            $this->assertEquals(200, $response->getStatusCode(), $uri);
            $this->assertPubliclyCached($response, 600);
            $this->assertEqualsCanonicalizing(['Accept', 'Authorization', 'Origin', 'X-Passwords'], $response->getVary());
        }
    }

    public static function getAcceptTypes(): array
    {
        return [
            ['application/json'],
            ['application/ld+json'],
        ];
    }

    public function testPublicationWithAuthorizationIsNotCacheable(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $token = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
        foreach ([
            '/publications/'.$publication->getId(),
            '/assets/'.$assetId,
        ] as $uri) {
            $response = $this->request($token, 'GET', $uri);
            $this->assertEquals(200, $response->getStatusCode(), $uri);
            $this->assertNotPubliclyCached($response);
        }
    }

    public function testPublicationWithPasswordsHeaderIsNotCacheable(): void
    {
        $publication = $this->createPublication();
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$publication->getId(), [], [], [
            'HTTP_X-Passwords' => base64_encode(json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR)),
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotPubliclyCached($response);
    }

    #[DataProvider('getNonCacheablePublications')]
    public function testNonCacheablePublication(array $options, int $publicationStatus): void
    {
        $publication = $this->createPublication($options);
        if (isset($options['securityMethod'])) {
            $publication->getConfig()->setSecurityMethod($options['securityMethod']);
            self::getEntityManager()->flush();
        }
        $assetId = $this->createAsset($publication);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$publication->getId());
        $this->assertEquals($publicationStatus, $response->getStatusCode());
        $this->assertNotPubliclyCached($response);

        $response = $this->request(null, 'GET', '/assets/'.$assetId);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotPubliclyCached($response);
    }

    public static function getNonCacheablePublications(): array
    {
        return [
            'disabled' => [['enabled' => false], 401],
            'expired' => [['endDate' => new \DateTimeImmutable('-1 hour')], 401],
            'not started' => [['startDate' => new \DateTimeImmutable('+1 hour')], 401],
            'password' => [['password' => 'secret'], 200],
            'authentication' => [['securityMethod' => Publication::SECURITY_METHOD_AUTHENTICATION], 200],
        ];
    }

    public function testChildOfSecuredPublicationIsNotCacheable(): void
    {
        $parent = $this->createPublication(['password' => 'secret']);
        $child = $this->createPublication(['parent' => $parent]);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$child->getId());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotPubliclyCached($response);
    }

    public function testMaxAgeDoesNotExceedExpiration(): void
    {
        $publication = $this->createPublication([
            'endDate' => new \DateTimeImmutable('+2 minutes'),
        ]);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', '/publications/'.$publication->getId());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $maxAge = (int) $response->headers->getCacheControlDirective('s-maxage');
        $this->assertGreaterThan(100, $maxAge);
        $this->assertLessThanOrEqual(120, $maxAge);
    }

    public function testUnknownPublicationIsNotCacheable(): void
    {
        $response = $this->request(null, 'GET', '/publications/unknown-slug');
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertNotPubliclyCached($response);
    }

    private function assertPubliclyCached(Response $response, int $maxAge): void
    {
        $this->assertTrue($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
        $this->assertSame((string) $maxAge, $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame((string) $maxAge, $response->headers->getCacheControlDirective('s-maxage'));
    }

    private function assertNotPubliclyCached(Response $response): void
    {
        $this->assertFalse($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }
}
