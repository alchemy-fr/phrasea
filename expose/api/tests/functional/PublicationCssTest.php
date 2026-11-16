<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class PublicationCssTest extends AbstractExposeTestCase
{
    public function testCssOfVisiblePublicationIsPubliclyCached(): void
    {
        $publication = $this->createPublication(['css' => '.foo {}']);
        $this->clearEmBeforeApiCall();

        $cssLink = $this->getCssLink($publication->getId(), null);
        $this->assertStringNotContainsString('jwt=', $cssLink);

        $response = $this->request(null, 'GET', $cssLink);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('.foo {}', $response->getContent());
        $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertLessThanOrEqual(600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testCssOfDisabledPublicationIsServedOnSignedUrlOnly(): void
    {
        $publication = $this->createPublication([
            'css' => '.foo {}',
            'enabled' => false,
            'ownerId' => KeycloakClientTestMock::USER_UID,
        ]);
        $this->clearEmBeforeApiCall();

        $response = $this->request(null, 'GET', sprintf('/publications/%s/style.%s.css', $publication->getId(), md5('.foo {}')));
        $this->assertEquals(404, $response->getStatusCode());

        $cssLink = $this->getCssLink($publication->getId(), KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::USER_UID));
        $this->assertStringContainsString('jwt=', $cssLink);

        $response = $this->request(null, 'GET', $cssLink);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('.foo {}', $response->getContent());
        $this->assertNotPubliclyCached($response);

        $response = $this->request(null, 'GET', preg_replace('#jwt=.+$#', 'jwt=invalid', $cssLink));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCssOfUnknownPublication(): void
    {
        foreach ([Uuid::uuid4()->toString(), 'not-a-uuid'] as $id) {
            $response = $this->request(null, 'GET', sprintf('/publications/%s/style.%s.css', $id, md5('.foo {}')));
            $this->assertEquals(404, $response->getStatusCode(), $id);
        }
    }

    private function getCssLink(string $publicationId, ?string $accessToken): string
    {
        $response = $this->request($accessToken, 'GET', '/publications/'.$publicationId);
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
        $json = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($json['cssLink'] ?? null);

        return $json['cssLink'];
    }

    private function assertNotPubliclyCached(Response $response): void
    {
        $this->assertFalse($response->headers->hasCacheControlDirective('public'), $response->headers->get('Cache-Control'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'), $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }
}
