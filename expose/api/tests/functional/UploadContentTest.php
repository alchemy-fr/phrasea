<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use App\Consumer\Handler\CheckPresignedUpload;
use App\Consumer\Handler\CheckPresignedUploadHandler;
use App\Entity\Asset;
use App\Entity\SubDefinition;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The content of the files sent straight to the storage is checked: on completion
 * for a multipart upload, once the URL expired for a presigned PUT.
 */
class UploadContentTest extends AbstractExposeTestCase
{
    private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const string HTML = '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>';

    private static function token(): string
    {
        return KeycloakClientTestMock::getJwtFor(KeycloakClientTestMock::ADMIN_UID);
    }

    /**
     * @return array{uploadId: string, parts: list<array{ETag: string, PartNumber: int}>}
     */
    private function sendMultipartUpload(string $content, string $filename, string $type): array
    {
        $response = $this->request(self::token(), 'POST', '/uploads', [
            'filename' => $filename,
            'type' => $type,
            'size' => strlen($content),
        ]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $upload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        /** @var HttpClientInterface $storageClient */
        $storageClient = self::getService('http_client');
        $headers = $storageClient->request('PUT', $upload['urls'][1], ['body' => $content])->getHeaders();

        return [
            'uploadId' => $upload['id'],
            'parts' => [['ETag' => $headers['etag'][0], 'PartNumber' => 1]],
        ];
    }

    private function postAsset(array $payload): Response
    {
        return $this->request(self::token(), 'POST', '/assets', [
            'publication_id' => $this->createPublication()->getId(),
        ] + $payload);
    }

    public function testAMultipartUploadOfAnotherTypeThanDeclaredIsRefusedAndDeleted(): void
    {
        $multipart = $this->sendMultipartUpload(self::HTML, 'photo.jpg', 'image/jpeg');
        $path = self::getEntityManager()->find(MultipartUpload::class, $multipart['uploadId'])->getPath();

        $response = $this->postAsset(['multipart' => $multipart]);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('content is "text/html"', json_decode((string) $response->getContent(), true)['detail']);
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(MultipartUpload::class, $multipart['uploadId']));
        $this->assertFalse(self::getStorageManager()->has($path));
        $this->assertSame(0, $em->getRepository(Asset::class)->count([]));
    }

    public function testAMultipartAssetGetsTheTypeOfItsContent(): void
    {
        $multipart = $this->sendMultipartUpload(base64_decode(self::PNG_BASE64), 'photo.png', 'image/jpeg');

        $response = $this->postAsset(['multipart' => $multipart]);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $id = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];
        $this->clearEmBeforeApiCall();
        $this->assertSame('image/png', self::getEntityManager()->find(Asset::class, $id)->getMimeType());
    }

    /**
     * Creates an asset to upload to a presigned URL, and the check sent for later.
     *
     * @return array{0: Asset, 1: CheckPresignedUpload}
     */
    private function createPresignedAsset(array $upload): array
    {
        /** @var TestTransport $transport */
        $transport = self::getService('messenger.transport.p1');
        $sent = $transport->intercept();
        try {
            $response = $this->postAsset(['upload' => $upload]);
        } finally {
            $transport->intercept(false);
        }
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($json['uploadURL']);

        $checks = array_values(array_filter($sent->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof CheckPresignedUpload));
        $this->assertCount(1, $checks);
        $delay = $checks[0]->last(DelayStamp::class)?->getDelay();
        $this->assertGreaterThan(1800 * 1000, $delay, 'Checked once the URL expired');

        $asset = self::getEntityManager()->find(Asset::class, $json['id']);
        $message = $checks[0]->getMessage();
        $this->assertSame([Asset::class, $asset->getId()], [$message->entityClass, $message->id]);

        return [$asset, $message];
    }

    public function testAPresignedUploadIsCheckedOnceTheUrlExpired(): void
    {
        [$asset, $message] = $this->createPresignedAsset(['name' => 'photo.jpg', 'type' => 'image/jpeg', 'size' => 1]);
        $png = base64_decode(self::PNG_BASE64);
        // As the PUT of the client
        self::getStorageManager()->store($asset->getPath(), $png);

        self::getService(CheckPresignedUploadHandler::class)($message);

        $this->clearEmBeforeApiCall();
        $asset = self::getEntityManager()->find(Asset::class, $asset->getId());
        $this->assertSame(strlen($png), $asset->getSize());
        $this->assertSame('image/png', $asset->getMimeType());
    }

    public function testAPresignedUploadOfAnotherTypeThanDeclaredIsDeleted(): void
    {
        [$asset, $message] = $this->createPresignedAsset(['name' => 'photo.jpg', 'type' => 'image/jpeg', 'size' => strlen(self::HTML)]);
        $id = $asset->getId();
        self::getStorageManager()->store($asset->getPath(), self::HTML);

        self::getService(CheckPresignedUploadHandler::class)($message);

        $this->clearEmBeforeApiCall();
        $this->assertNull(self::getEntityManager()->find(Asset::class, $id));
    }

    public function testAPresignedUploadNeverSentIsLeftAsIs(): void
    {
        [$asset, $message] = $this->createPresignedAsset(['name' => 'photo.jpg', 'type' => 'image/jpeg', 'size' => 10]);

        self::getService(CheckPresignedUploadHandler::class)($message);

        $this->clearEmBeforeApiCall();
        $this->assertSame(10, self::getEntityManager()->find(Asset::class, $asset->getId())->getSize());
    }

    public function testAPresignedSubDefinitionIsChecked(): void
    {
        $publication = $this->createPublication();
        $assetId = $this->createAsset($publication);

        /** @var TestTransport $transport */
        $transport = self::getService('messenger.transport.p1');
        $sent = $transport->intercept();
        try {
            $response = $this->request(self::token(), 'POST', '/sub-definitions', [
                'asset_id' => $assetId,
                'name' => 'preview',
                'upload' => ['name' => 'preview.mp4', 'type' => 'video/mp4', 'size' => 10],
            ]);
        } finally {
            $transport->intercept(false);
        }
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $id = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];
        $messages = array_values(array_filter(array_map(static fn ($envelope): object => $envelope->getMessage(), $sent->getSent()), static fn (object $message): bool => $message instanceof CheckPresignedUpload));
        $this->assertCount(1, $messages);
        $this->assertSame(SubDefinition::class, $messages[0]->entityClass);

        $subDefinition = self::getEntityManager()->find(SubDefinition::class, $id);
        self::getStorageManager()->store($subDefinition->getPath(), "#EXTM3U\n#EXTINF:10,\nfile:///etc/passwd\n");

        self::getService(CheckPresignedUploadHandler::class)($messages[0]);

        $this->clearEmBeforeApiCall();
        $this->assertNull(self::getEntityManager()->find(SubDefinition::class, $id));
    }

    public function testAnUploadOfATypeWithoutKnownExtension(): void
    {
        $response = $this->postAsset(['upload' => ['type' => 'application/x-unknown-type', 'size' => 10]]);

        // Refused or not depending on the allowed types, never an error
        $this->assertLessThan(500, $response->getStatusCode(), (string) $response->getContent());
    }
}
