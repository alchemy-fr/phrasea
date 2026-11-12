<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The server decides the part size and presigns every part when the upload
 * is created; "/parts" hands out fresh URLs from a given part (resume / expiry).
 */
class MultipartUploadTest extends AbstractUploaderTestCase
{
    private const SIZE = 45 * 1024 * 1024;

    private function authHeaders(string $userId = KeycloakClientTestMock::ADMIN_UID): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function createUpload(int $size, string $userId = KeycloakClientTestMock::ADMIN_UID): array
    {
        $response = self::createClient()->request('POST', '/uploads', [
            'headers' => $this->authHeaders($userId),
            'json' => [
                'filename' => 'file.jpg',
                'type' => 'image/jpeg',
                'size' => $size,
            ],
        ]);
        $this->assertSame(201, $response->getStatusCode());

        return $response->toArray();
    }

    /**
     * Sends the first part straight to the storage, as the clients do.
     *
     * @return array{uploadId: string, parts: list<array{ETag: string, PartNumber: int}>}
     */
    private static function sendSinglePart(array $upload, string $content): array
    {
        $headers = self::getStorageClient()->request('PUT', $upload['urls'][1], [
            'body' => $content,
        ])->getHeaders();

        return [
            'uploadId' => $upload['id'],
            'parts' => [
                ['ETag' => $headers['etag'][0], 'PartNumber' => 1],
            ],
        ];
    }

    private function completeAsAsset(array $multipart): ResponseInterface
    {
        return self::createClient()->request('POST', '/assets', [
            'headers' => $this->authHeaders(),
            'json' => [
                'targetSlug' => $this->getOrCreateDefaultTarget()->getSlug(),
                'multipart' => $multipart,
            ],
        ]);
    }

    public function testCreateUploadReturnsThePlanAndEveryPartUrl(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/uploads', [
            'headers' => $this->authHeaders(),
            'json' => [
                'filename' => 'big.jpg',
                'type' => 'image/jpeg',
                'size' => self::SIZE,
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $data = $response->toArray();

        $this->assertMatchesUuid($data['id']);
        $this->assertSame(self::SIZE, $data['size']);
        $this->assertFalse($data['complete']);
        $this->assertIsInt($data['chunkSize']);
        $this->assertGreaterThanOrEqual(5 * 1024 * 1024, $data['chunkSize'], 'S3 needs parts of at least 5MB');

        $partCount = (int) ceil(self::SIZE / $data['chunkSize']);
        $this->assertGreaterThan(1, $partCount, 'The fixture must span several parts');
        $this->assertCount($partCount, $data['urls']);
        $this->assertSame(range(1, $partCount), array_map('intval', array_keys($data['urls'])));
        foreach ($data['urls'] as $partNumber => $url) {
            $this->assertStringContainsString('partNumber='.$partNumber, $url);
            $this->assertStringContainsString('uploadId=', $url);
            $this->assertStringContainsString('X-Amz-Signature=', $url);
        }

        // Fresh URLs from a given part, to resume or after expiry
        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => 2],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $plan = $response->toArray();
        $this->assertSame($data['chunkSize'], $plan['chunkSize']);
        $this->assertSame($partCount, $plan['partCount']);
        $this->assertSame(range(2, $partCount), array_map('intval', array_keys($plan['urls'])));

        // Past the last part: nothing left to upload, not an error
        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => $partCount + 1],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->toArray()['urls']);

        $response = $client->request('POST', '/uploads/'.$data['id'].'/parts', [
            'headers' => $this->authHeaders(),
            'json' => ['from' => 0],
        ]);
        $this->assertSame(400, $response->getStatusCode());

        // Legacy single-part endpoint still works
        $response = $client->request('POST', '/uploads/'.$data['id'].'/part', [
            'headers' => $this->authHeaders(),
            'json' => ['part' => 1],
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('partNumber=1', $response->toArray()['url']);

        // Cancelling releases the S3 upload
        $response = $client->request('DELETE', '/uploads/'.$data['id'], [
            'headers' => $this->authHeaders(),
        ]);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCreateUploadRequiresAuthentication(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/uploads', [
            'json' => [
                'filename' => 'big.jpg',
                'type' => 'image/jpeg',
                'size' => self::SIZE,
            ],
        ]);

        $this->assertSame(401, $response->getStatusCode());
    }

    public static function getOwnerOperations(): iterable
    {
        yield 'get' => ['GET', '', null];
        yield 'parts' => ['POST', '/parts', []];
        yield 'part (deprecated)' => ['POST', '/part', ['part' => 1]];
        yield 'cancel' => ['DELETE', '', null];
    }

    #[DataProvider('getOwnerOperations')]
    public function testAnotherUserCannotUseTheUpload(string $method, string $route, ?array $payload): void
    {
        $upload = $this->createUpload(self::SIZE);

        $response = self::createClient()->request($method, '/uploads/'.$upload['id'].$route, [
            'headers' => $this->authHeaders(KeycloakClientTestMock::USER_UID),
        ] + (null !== $payload ? ['json' => $payload] : []));

        $this->assertSame(403, $response->getStatusCode());
    }

    public static function getInvalidPartNumbers(): iterable
    {
        yield 'zero' => [0];
        yield 'past the last part' => [(int) ceil(self::SIZE / (5 * 1024 * 1024)) + 1];
    }

    #[DataProvider('getInvalidPartNumbers')]
    public function testDeprecatedSinglePartUrlIsBoundedToThePartCount(int $part): void
    {
        $upload = $this->createUpload(self::SIZE);
        $this->assertSame(5 * 1024 * 1024, $upload['chunkSize'], 'The fixture assumes parts of 5MB');

        $response = self::createClient()->request('POST', '/uploads/'.$upload['id'].'/part', [
            'headers' => $this->authHeaders(),
            'json' => ['part' => $part],
        ]);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testAnUploadIsCompletedOnce(): void
    {
        $content = 'Hello, multipart!';
        $upload = $this->createUpload(strlen($content));
        $multipart = self::sendSinglePart($upload, $content);

        $response = $this->completeAsAsset($multipart);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(strlen($content), $response->toArray()['size']);

        $response = $this->completeAsAsset($multipart);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('already complete', $response->toArray(false)['detail']);

        // No more part URL for a completed upload
        $response = self::createClient()->request('POST', '/uploads/'.$upload['id'].'/part', [
            'headers' => $this->authHeaders(),
            'json' => ['part' => 1],
        ]);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testAnotherUserCannotCompleteTheUpload(): void
    {
        $content = 'Hello, multipart!';
        $upload = $this->createUpload(strlen($content), KeycloakClientTestMock::USER_UID);
        $multipart = self::sendSinglePart($upload, $content);

        $response = $this->completeAsAsset($multipart);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAFileBiggerThanDeclaredIsRefusedAndDeleted(): void
    {
        $upload = $this->createUpload(1);
        $multipart = self::sendSinglePart($upload, str_repeat('x', 1024));
        $path = self::getEntityManager()->find(MultipartUpload::class, $upload['id'])->getPath();

        $response = $this->completeAsAsset($multipart);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('does not match the declared size', $response->toArray(false)['detail']);
        $em = self::getEntityManager();
        $em->clear();
        $this->assertNull($em->find(MultipartUpload::class, $upload['id']));
        $this->assertFalse(self::getService(FileStorageManager::class)->has($path));
    }
}
