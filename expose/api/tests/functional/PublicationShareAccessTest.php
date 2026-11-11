<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Asset;
use App\Entity\Publication;
use App\Security\ShareTokenAuthorizer;
use Symfony\Component\HttpFoundation\Response;

class PublicationShareAccessTest extends AbstractExposeTestCase
{
    public function testShareOpensAProtectedDisabledPublication(): void
    {
        $publication = $this->createPublication([
            'slug' => 'secret-pub',
            'enabled' => false,
            'password' => 'secret',
            'downloadEnabled' => true,
        ]);
        $publicationId = $publication->getId();
        $assetId = $this->createAsset($publication);
        $this->setAssetSlug($assetId, 'first-asset');
        $share = $this->createShare($publicationId);
        $this->clearEmBeforeApiCall();

        // Without the token
        $this->assertSame(401, $this->get('/publications/'.$publicationId)->getStatusCode());
        $this->assertSame(404, $this->get('/publications/secret-pub/assets/first-asset')->getStatusCode());
        $this->assertArrayNotHasKey('url', $this->getJson('/assets/'.$assetId));

        $headers = $this->shareHeaders(str_repeat('x', 64), $share->getToken());
        $json = $this->getJson('/publications/'.$publicationId, $headers);
        $this->assertTrue($json['authorized']);
        $this->assertNull($json['authorizationError'] ?? null);
        $this->assertCount(1, $json['assets']);
        $this->assertFalse($json['capabilities']['edit']);
        $this->assertFalse($json['capabilities']['operator']);

        $this->assertTrue($this->getJson('/publications/secret-pub', $headers)['authorized']);

        $asset = $this->getJson('/assets/'.$assetId, $headers);
        $this->assertNotEmpty($asset['url']);
        $this->assertNotEmpty($asset['downloadUrl']);

        $response = $this->get('/publications/secret-pub/assets/first-asset', $headers);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));
        $this->assertSame($assetId, json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['id']);
    }

    public function testShareSkipsTheAuthenticationMethod(): void
    {
        $publication = $this->createPublication();
        $publication->getConfig()->setSecurityMethod(Publication::SECURITY_METHOD_AUTHENTICATION);
        self::getEntityManager()->flush();
        $share = $this->createShare($publication->getId());
        $this->clearEmBeforeApiCall();

        $this->assertFalse($this->getJson('/publications/'.$publication->getId())['authorized']);
        $this->assertTrue($this->getJson('/publications/'.$publication->getId(), $this->shareHeaders($share->getToken()))['authorized']);
    }

    public function testInactiveOrForeignSharesGiveNoAccess(): void
    {
        $publicationId = $this->createPublication(['password' => 'secret', 'enabled' => false])->getId();
        $otherId = $this->createPublication()->getId();
        $protectedId = $this->createPublication(['password' => 'secret'])->getId();
        $expiredOfProtected = $this->createShare($protectedId, ['expiresAt' => new \DateTimeImmutable('-1 minute')])->getToken();
        $tokens = [
            'expired' => $this->createShare($publicationId, ['expiresAt' => new \DateTimeImmutable('-1 minute')])->getToken(),
            'revoked' => $this->createShare($publicationId, ['revoked' => true])->getToken(),
            'other publication' => $this->createShare($otherId)->getToken(),
            'unknown' => str_repeat('a', 64),
            'too long' => $this->createShare($publicationId)->getToken().'x',
        ];
        $this->clearEmBeforeApiCall();

        foreach ($tokens as $case => $token) {
            $response = $this->get('/publications/'.$publicationId, $this->shareHeaders($token));
            $this->assertSame(401, $response->getStatusCode(), $case);
        }

        $json = $this->getJson('/publications/'.$protectedId, $this->shareHeaders($expiredOfProtected));
        $this->assertFalse($json['authorized']);
        $this->assertArrayNotHasKey('assets', $json);
    }

    public function testShareDoesNotOpenTheSubPublications(): void
    {
        $parent = $this->createPublication(['password' => 'secret']);
        $child = $this->createPublication(['parent' => $parent, 'enabled' => false]);
        $protectedChild = $this->createPublication(['parent' => $parent]);
        $share = $this->createShare($parent->getId());
        $this->clearEmBeforeApiCall();
        $headers = $this->shareHeaders($share->getToken());

        $json = $this->getJson('/publications/'.$parent->getId(), $headers);
        $this->assertTrue($json['authorized']);
        // The disabled child is hidden, the other one is listed
        $this->assertSame([$protectedChild->getId()], array_column($json['children'], 'id'));

        $this->assertSame(401, $this->get('/publications/'.$child->getId(), $headers)->getStatusCode());
        // The child inherits the password of its parent
        $json = $this->getJson('/publications/'.$protectedChild->getId(), $headers);
        $this->assertFalse($json['authorized']);
    }

    public function testDownloadSettingsStillApply(): void
    {
        $publication = $this->createPublication(['password' => 'secret']);
        $assetId = $this->createAsset($publication);
        $share = $this->createShare($publication->getId());
        $this->clearEmBeforeApiCall();
        $headers = $this->shareHeaders($share->getToken());

        $json = $this->getJson('/publications/'.$publication->getId(), $headers);
        $this->assertTrue($json['authorized']);
        $this->assertNull($json['archiveDownloadUrl'] ?? null);
        $asset = $this->getJson('/assets/'.$assetId, $headers);
        $this->assertNull($asset['downloadUrl'] ?? null);
        $this->assertNull($asset['url'] ?? null);
    }

    public function testShareResponsesAreNotPubliclyCached(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);
        $share = $this->createShare($publication->getId());
        $this->clearEmBeforeApiCall();

        foreach (['/publications/'.$publication->getId(), '/assets/'.$assetId] as $uri) {
            $response = $this->get($uri, $this->shareHeaders($share->getToken()));
            $this->assertSame(200, $response->getStatusCode(), $uri);
            $this->assertFalse($response->headers->hasCacheControlDirective('public'), $uri);

            $response = $this->get($uri);
            $this->assertTrue($response->headers->hasCacheControlDirective('public'), $uri);
            $this->assertContains(ShareTokenAuthorizer::HEADER_NAME, $response->getVary(), $uri);
        }
    }

    public function testHeaderParsing(): void
    {
        $this->assertSame([], ShareTokenAuthorizer::parseHeader(null));
        $this->assertSame([], ShareTokenAuthorizer::parseHeader(''));
        $this->assertSame(['a', 'b'], ShareTokenAuthorizer::parseHeader(' a ,b,,a, '.str_repeat('c', 65)));
        $this->assertCount(ShareTokenAuthorizer::MAX_TOKENS, ShareTokenAuthorizer::parseHeader(implode(',', range(1, 30))));
    }

    private function setAssetSlug(string $assetId, string $slug): void
    {
        $asset = self::getEntityManager()->find(Asset::class, $assetId);
        $asset->setSlug($slug);
        self::getEntityManager()->flush();
    }

    /**
     * @return array<string, string>
     */
    private function shareHeaders(string ...$tokens): array
    {
        return ['HTTP_X-Share-Tokens' => implode(',', $tokens)];
    }

    private function get(string $uri, array $headers = []): Response
    {
        return $this->request(null, 'GET', $uri, [], [], $headers);
    }

    private function getJson(string $uri, array $headers = []): array
    {
        $response = $this->get($uri, $headers);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function describe(Response $response): string
    {
        return substr((string) $response->getContent(), 0, 800);
    }
}
