<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Security\UriJwtManager;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\NotifierBundle\Message\SendEmailNotification;
use App\Consumer\Handler\DownloadRequest as DownloadRequestMessage;
use App\Consumer\Handler\DownloadRequestHandler;
use App\Consumer\Handler\PublicationArchiveRequest;
use App\Consumer\Handler\PublicationArchiveRequestHandler;
use App\Security\PublicationDownloadPolicy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class PublicationDownloadTest extends AbstractExposeTestCase
{
    private const string BASE_URI = 'http://localhost';

    public function testDisabledDownloadIsDenied(): void
    {
        [$publicationId, $assetId, $subDefId] = $this->createDownloadablePublication([]);

        $json = $this->getJson('/publications/'.$publicationId);
        $this->assertNull($json['archiveDownloadUrl'] ?? null);
        $asset = $this->getJson('/assets/'.$assetId);
        foreach (['url', 'downloadUrl', 'previewUrl', 'thumbUrl'] as $key) {
            $this->assertNull($asset[$key] ?? null, sprintf('Asset exposes its %s', $key));
        }
        $this->assertCount(1, $asset['subDefinitions']);
        $this->assertNull($asset['subDefinitions'][0]['url'] ?? null);
        $this->assertNull($asset['subDefinitions'][0]['downloadUrl'] ?? null);

        foreach ($this->getDirectDownloadUris($publicationId, $assetId, $subDefId) as $uri) {
            $this->assertSame(403, $this->request(null, 'GET', $uri)->getStatusCode(), $uri);
            $this->assertSame(403, $this->request(null, 'GET', $this->getPolicy()->signDirectDownloadUri($uri))->getStatusCode(), $uri);
            $this->assertSame(403, $this->request(null, 'GET', $this->getPolicy()->signEmailDownloadUri($uri, 60))->getStatusCode(), $uri);
        }

        foreach ($this->getEmailRequestUris($publicationId, $assetId, $subDefId) as $uri) {
            $response = $this->request(null, 'POST', $this->getUriJwtManager()->signUri($uri), [
                'email' => 'foo@example.com',
                'acceptTerms' => true,
            ]);
            $this->assertSame(403, $response->getStatusCode(), $uri);
        }

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', [
            'assetId' => $assetId,
            'acceptTerms' => true,
        ]);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testEditorsAreNotRestricted(): void
    {
        [$publicationId, $assetId, $subDefId] = $this->createDownloadablePublication([]);
        $adminToken = KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);

        $asset = $this->getJson('/assets/'.$assetId, $adminToken);
        $this->assertNotEmpty($asset['url'] ?? null);
        $this->assertNotEmpty($asset['previewUrl'] ?? null);
        $this->assertNotEmpty($asset['subDefinitions'][0]['url'] ?? null);
        $this->assertSame(302, $this->request($adminToken, 'GET', $asset['downloadUrl'])->getStatusCode());
        $this->assertNotEmpty($this->getJson('/publications/'.$publicationId, $adminToken)['archiveDownloadUrl'] ?? null);

        foreach ($this->getDirectDownloadUris($publicationId, $assetId, $subDefId) as $uri) {
            $this->assertSame(302, $this->request($adminToken, 'GET', $uri)->getStatusCode(), $uri);
        }

        // The sent messages are reset with the services at each request.
        $transport = $this->interceptTransport();
        $sent = [];
        foreach ($this->getEmailRequestUris($publicationId, $assetId, $subDefId) as $uri) {
            $response = $this->request($adminToken, 'POST', $uri, ['email' => 'foo@example.com']);
            $this->assertSame(200, $response->getStatusCode(), $uri);
            array_push($sent, ...$this->getSentMessages($transport, DownloadRequestMessage::class), ...$this->getSentMessages($transport, PublicationArchiveRequest::class));
        }
        $this->assertSame([DownloadRequestMessage::class, DownloadRequestMessage::class, PublicationArchiveRequest::class], array_map(get_class(...), $sent));
    }

    public function testNominalDirectDownload(): void
    {
        [$publicationId, $assetId] = $this->createDownloadablePublication(['downloadEnabled' => true]);

        $publication = $this->getJson('/publications/'.$publicationId);
        $this->assertSame(302, $this->request(null, 'GET', $publication['archiveDownloadUrl'])->getStatusCode());

        $asset = $this->getJson('/assets/'.$assetId);
        $this->assertNotEmpty($asset['url'] ?? null);
        $this->assertNotEmpty($asset['previewUrl'] ?? null);
        $this->assertNotEmpty($asset['subDefinitions'][0]['url'] ?? null);

        $this->assertSame(302, $this->request(null, 'GET', $asset['downloadUrl'])->getStatusCode());
        $this->assertSame(302, $this->request(null, 'GET', $asset['subDefinitions'][0]['downloadUrl'])->getStatusCode());

        // A URL signed for another purpose (consultation, subtitles…) does not download.
        $uri = self::BASE_URI.'/publications/'.$publicationId.'/assets/'.$assetId.'/download';
        $this->assertSame(403, $this->request(null, 'GET', $uri)->getStatusCode());
        $this->assertSame(403, $this->request(null, 'GET', $this->getUriJwtManager()->signUri($uri))->getStatusCode());
        $this->assertSame(403, $this->request(null, 'GET', $uri.'?jwt=invalid')->getStatusCode());

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', [
            'assetId' => $assetId,
        ]);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDownloadViaEmailOnly(): void
    {
        [$publicationId, $assetId, $subDefId] = $this->createDownloadablePublication([
            'downloadEnabled' => true,
            'downloadViaEmail' => true,
        ]);

        $asset = $this->getJson('/assets/'.$assetId);
        $this->assertNull($asset['url'] ?? null);
        $this->assertNull($asset['previewUrl'] ?? null);
        $this->assertNull($asset['subDefinitions'][0]['url'] ?? null);
        $this->assertStringContainsString('/download-request?jwt=', $asset['downloadUrl']);
        $this->assertStringContainsString('/download-request?jwt=', $asset['subDefinitions'][0]['downloadUrl']);
        $this->assertStringContainsString('/archive/download-request?jwt=', $this->getJson('/publications/'.$publicationId)['archiveDownloadUrl']);

        foreach ($this->getDirectDownloadUris($publicationId, $assetId, $subDefId) as $uri) {
            $this->assertSame(403, $this->request(null, 'GET', $this->getPolicy()->signDirectDownloadUri($uri))->getStatusCode(), $uri);
            $this->assertSame(302, $this->request(null, 'GET', $this->getPolicy()->signEmailDownloadUri($uri, 60))->getStatusCode(), $uri);
        }

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', [
            'assetId' => $assetId,
            'acceptTerms' => true,
        ]);
        $this->assertSame(400, $response->getStatusCode());

        // The link sent by email downloads the file.
        $transport = $this->interceptTransport();
        $response = $this->request(null, 'POST', $asset['downloadUrl'], ['email' => 'foo@example.com']);
        $this->assertSame(200, $response->getStatusCode());
        $requests = $this->getSentMessages($transport, DownloadRequestMessage::class);
        $this->assertCount(1, $requests);

        $transport->reset();
        /** @var DownloadRequestHandler $handler */
        $handler = self::getService(DownloadRequestHandler::class);
        $handler($requests[0]);
        $emails = $this->getSentMessages($transport, SendEmailNotification::class);
        $this->assertCount(1, $emails);
        $this->assertSame(302, $this->request(null, 'GET', $emails[0]->params['downloadUrl'])->getStatusCode());
    }

    public function testArchiveLinkSentByEmailDownloads(): void
    {
        [$publicationId] = $this->createDownloadablePublication([
            'downloadEnabled' => true,
            'downloadViaEmail' => true,
        ]);

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'POST', $this->getJson('/publications/'.$publicationId)['archiveDownloadUrl'], [
            'email' => 'foo@example.com',
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $requests = $this->getSentMessages($transport, PublicationArchiveRequest::class);
        $this->assertCount(1, $requests);

        $transport->reset();
        /** @var PublicationArchiveRequestHandler $handler */
        $handler = self::getService(PublicationArchiveRequestHandler::class);
        $handler($requests[0]);
        $emails = $this->getSentMessages($transport, SendEmailNotification::class);
        $this->assertCount(1, $emails);
        $this->assertSame(302, $this->request(null, 'GET', $emails[0]->params['downloadUrl'])->getStatusCode());
    }

    public function testDownloadTermsAreAcceptedFirst(): void
    {
        [$publicationId, $assetId, $subDefId] = $this->createDownloadablePublication([
            'downloadEnabled' => true,
            'downloadTerms' => true,
        ]);

        $publication = $this->getJson('/publications/'.$publicationId);
        $this->assertSame(403, $this->request(null, 'GET', $publication['archiveDownloadUrl'])->getStatusCode());
        $asset = $this->getJson('/assets/'.$assetId);
        $this->assertNull($asset['url'] ?? null);
        $this->assertNull($asset['previewUrl'] ?? null);
        $this->assertNull($asset['subDefinitions'][0]['url'] ?? null);
        $this->assertSame(403, $this->request(null, 'GET', $asset['downloadUrl'])->getStatusCode());
        $this->assertSame(403, $this->request(null, 'GET', $asset['subDefinitions'][0]['downloadUrl'])->getStatusCode());

        foreach ([['assetId' => $assetId], ['subDefId' => $subDefId], ['archive' => true]] as $target) {
            $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', $target);
            $this->assertSame(400, $response->getStatusCode(), json_encode($target, JSON_THROW_ON_ERROR));

            $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', $target + ['acceptTerms' => 'yes']);
            $this->assertSame(400, $response->getStatusCode(), json_encode($target, JSON_THROW_ON_ERROR));

            $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', $target + ['acceptTerms' => true]);
            $this->assertSame(200, $response->getStatusCode(), json_encode($target, JSON_THROW_ON_ERROR));
            $url = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['url'];
            $this->assertSame(302, $this->request(null, 'GET', $url)->getStatusCode(), $url);
        }

        $transport = $this->interceptTransport();
        $response = $this->request(null, 'POST', $this->getUriJwtManager()->signUri(self::BASE_URI.'/publications/'.$publicationId.'/assets/'.$assetId.'/download-request'), [
            'email' => 'foo@example.com',
        ]);
        $this->assertSame(400, $response->getStatusCode());
        $response = $this->request(null, 'POST', $this->getUriJwtManager()->signUri(self::BASE_URI.'/publications/'.$publicationId.'/archive/download-request'), [
            'email' => 'foo@example.com',
            'acceptTerms' => true,
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $this->getSentMessages($transport, PublicationArchiveRequest::class));
    }

    public function testDownloadUrlRequiresATargetOfThePublication(): void
    {
        [$publicationId, $assetId] = $this->createDownloadablePublication(['downloadEnabled' => true]);
        [, $otherAssetId] = $this->createDownloadablePublication(['downloadEnabled' => true]);

        foreach ([[], ['assetId' => $assetId, 'archive' => true], ['assetId' => 42], ['archive' => 'yes']] as $body) {
            $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', $body);
            $this->assertSame(400, $response->getStatusCode(), json_encode($body, JSON_THROW_ON_ERROR));
        }

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', ['assetId' => $otherAssetId]);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPasswordIsStillRequired(): void
    {
        [$publicationId, $assetId] = $this->createDownloadablePublication([
            'downloadEnabled' => true,
            'downloadTerms' => true,
            'password' => 's3cr3t',
        ]);

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', [
            'assetId' => $assetId,
            'acceptTerms' => true,
        ]);
        $this->assertContains($response->getStatusCode(), [401, 403]);

        $response = $this->request(null, 'POST', '/publications/'.$publicationId.'/download-url', [
            'assetId' => $assetId,
            'acceptTerms' => true,
        ], [], [
            'HTTP_X-Passwords' => base64_encode(json_encode([$publicationId => 's3cr3t'], JSON_THROW_ON_ERROR)),
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $url = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['url'];
        $this->assertSame(302, $this->request(null, 'GET', $url)->getStatusCode());
    }

    /**
     * @return array{string, string, string} the IDs of the publication, its asset and the rendition of the asset
     */
    private function createDownloadablePublication(array $options): array
    {
        $publication = $this->createPublication($options);
        $assetId = $this->createAsset($publication);
        $subDefId = $this->createSubDefinition($assetId, ['name' => 'hd']);
        $publicationId = $publication->getId();
        $this->clearEmBeforeApiCall();

        return [$publicationId, $assetId, $subDefId];
    }

    /**
     * @return list<string>
     */
    private function getDirectDownloadUris(string $publicationId, string $assetId, string $subDefId): array
    {
        return [
            self::BASE_URI.'/publications/'.$publicationId.'/assets/'.$assetId.'/download',
            self::BASE_URI.'/publications/'.$publicationId.'/subdef/'.$subDefId.'/download',
            self::BASE_URI.'/publications/'.$publicationId.'/archive',
        ];
    }

    /**
     * @return list<string>
     */
    private function getEmailRequestUris(string $publicationId, string $assetId, string $subDefId): array
    {
        return [
            self::BASE_URI.'/publications/'.$publicationId.'/assets/'.$assetId.'/download-request',
            self::BASE_URI.'/publications/'.$publicationId.'/subdef/'.$subDefId.'/download-request',
            self::BASE_URI.'/publications/'.$publicationId.'/archive/download-request',
        ];
    }

    private function getJson(string $uri, ?string $accessToken = null): array
    {
        $response = $this->request($accessToken, 'GET', $uri);
        $this->assertSame(200, $response->getStatusCode(), $this->describe($response));

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function describe(Response $response): string
    {
        return substr((string) $response->getContent(), 0, 500);
    }

    private function getPolicy(): PublicationDownloadPolicy
    {
        return self::getService(PublicationDownloadPolicy::class);
    }

    private function getUriJwtManager(): UriJwtManager
    {
        return self::getService(UriJwtManager::class);
    }

    private function interceptTransport(): InMemoryTransport
    {
        /** @var TestTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.p1');

        return $transport->intercept();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function getSentMessages(InMemoryTransport $transport, string $class): array
    {
        return array_values(array_filter(
            array_map(fn ($envelope) => $envelope->getMessage(), $transport->getSent()),
            fn (object $message): bool => $message instanceof $class,
        ));
    }
}
