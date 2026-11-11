<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Entity\Asset;
use App\Entity\Publication;
use App\Http\Cache\PublicHttpCachePolicy;
use Symfony\Component\HttpFoundation\Response;

class AssetWebVTTTest extends AbstractExposeTestCase
{
    private const string CONTENT = "WEBVTT\n\n00:00.000 --> 00:01.000\nHello";

    public function testPublicWebVTTIsCacheable(): void
    {
        $publication = $this->createPublication();
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);

        $response = $this->request(null, 'GET', $this->getWebVTTUri($assetId, $vttId));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::CONTENT, $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertSame((string) PublicHttpCachePolicy::MAX_AGE, $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame((string) PublicHttpCachePolicy::MAX_AGE, $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertVary($response);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));

        // The link given to the player is not signed.
        $this->assertStringNotContainsString('jwt=', $this->getWebVTTLink($assetId));
    }

    public function testMaxAgeDoesNotExceedTheExpirationOfAnAncestor(): void
    {
        $parent = $this->createPublication(['endDate' => new \DateTimeImmutable('+2 minutes')]);
        $publication = $this->createPublication(['parent' => $parent]);
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);

        $response = $this->request(null, 'GET', $this->getWebVTTUri($assetId, $vttId));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $maxAge = (int) $response->headers->getCacheControlDirective('s-maxage');
        $this->assertGreaterThan(100, $maxAge);
        $this->assertLessThanOrEqual(120, $maxAge);
    }

    public function testWebVTTWithCredentialsIsNotCacheable(): void
    {
        $publication = $this->createPublication();
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);
        $uri = $this->getWebVTTUri($assetId, $vttId);

        foreach ([
            'authorization' => [KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID), []],
            'passwords' => [null, ['HTTP_X-Passwords' => base64_encode(json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR))]],
            'share tokens' => [null, ['HTTP_X-Share-Tokens' => 'foo']],
        ] as $case => [$token, $server]) {
            $response = $this->request($token, 'GET', $uri, [], [], $server);
            $this->assertSame(200, $response->getStatusCode(), $case);
            $this->assertNotCacheable($response);
        }
    }

    public function testProtectedWebVTTIsNotCacheable(): void
    {
        $publication = $this->createPublication(['password' => 's3cr3t']);
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);

        $this->assertContains($this->request(null, 'GET', $this->getWebVTTUri($assetId, $vttId))->getStatusCode(), [401, 403]);

        $link = $this->getWebVTTLink($assetId, [$publication->getId() => 's3cr3t']);
        $this->assertStringContainsString('jwt=', $link);
        $response = $this->request(null, 'GET', $link);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotCacheable($response);
    }

    public function testTheWebVTTOfAChildOfAProtectedPublicationIsSigned(): void
    {
        $parent = $this->createPublication(['password' => 's3cr3t']);
        $publication = $this->createPublication(['parent' => $parent]);
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);

        $this->assertContains($this->request(null, 'GET', $this->getWebVTTUri($assetId, $vttId))->getStatusCode(), [401, 403]);

        $link = $this->getWebVTTLink($assetId, [$parent->getId() => 's3cr3t']);
        $this->assertStringContainsString('jwt=', $link);
        $response = $this->request(null, 'GET', $link);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotCacheable($response);
    }

    public function testTheWebVTTOfAChildOfAHiddenPublicationIsDenied(): void
    {
        $parent = $this->createPublication(['enabled' => false]);
        $publication = $this->createPublication(['parent' => $parent]);
        [$assetId, $vttId] = $this->createAssetWithWebVTT($publication);

        $this->assertContains($this->request(null, 'GET', $this->getWebVTTUri($assetId, $vttId))->getStatusCode(), [401, 403]);
    }

    /**
     * @return array{string, string} the IDs of the asset and of its WebVTT
     */
    private function createAssetWithWebVTT(Publication $publication): array
    {
        $assetId = $this->createAsset($publication);
        $em = self::getEntityManager();
        $asset = $em->find(Asset::class, $assetId);
        $asset->setWebVTT([[
            'label' => 'English',
            'locale' => 'en',
            'content' => self::CONTENT,
        ]]);
        $em->flush();
        $vttId = $asset->getWebVTT()[0]['id'];
        $this->clearEmBeforeApiCall();

        return [$assetId, $vttId];
    }

    private function getWebVTTUri(string $assetId, string $vttId): string
    {
        return sprintf('/publications/%s/vtt/%s.vtt', $assetId, $vttId);
    }

    /**
     * @param array<string, string> $passwords
     */
    private function getWebVTTLink(string $assetId, array $passwords = []): string
    {
        $server = [] !== $passwords ? ['HTTP_X-Passwords' => base64_encode(json_encode($passwords, JSON_THROW_ON_ERROR))] : [];
        $response = $this->request(null, 'GET', '/assets/'.$assetId, [], [], $server);
        $this->assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $json['webVTTLinks'] ?? []);

        return $json['webVTTLinks'][0]['url'];
    }

    private function assertNotCacheable(Response $response): void
    {
        $this->assertFalse($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertVary($response);
    }

    private function assertVary(Response $response): void
    {
        foreach (['Authorization', 'X-Passwords', 'X-Share-Tokens'] as $header) {
            $this->assertContains($header, $response->getVary());
        }
    }
}
