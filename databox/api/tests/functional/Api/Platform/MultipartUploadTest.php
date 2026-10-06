<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Platform;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\StorageBundle\Entity\MultipartUpload;
use ApiPlatform\Test\Client;
use App\Tests\Functional\AbstractDataboxTestCase;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * S3 multipart uploads (/uploads).
 *
 * The S3 client is not reached: its terminal handler is replaced by a stub
 * recording the commands (presigned URLs are signed locally and need no
 * network). The kernel is not rebooted between requests so that the stub
 * stays in place for the whole test.
 */
final class MultipartUploadTest extends AbstractDataboxTestCase
{
    private const string S3_UPLOAD_ID = 'mock-s3-upload-id';

    private Client $client;

    /**
     * @var array<array{name: string, args: array}>
     */
    private array $s3Commands = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->getKernelBrowser()->disableReboot();

        /** @var S3Client $s3Client */
        $s3Client = static::getContainer()->get('alchemy_storage.s3_client');
        $s3Client->getHandlerList()->setHandler(function (CommandInterface $command) {
            $this->s3Commands[] = [
                'name' => $command->getName(),
                'args' => $command->toArray(),
            ];

            return Create::promiseFor(new Result(
                'CreateMultipartUpload' === $command->getName() ? ['UploadId' => self::S3_UPLOAD_ID] : []
            ));
        });
    }

    private function headers(string $userId = KeycloakClientTestMock::USER_UID): array
    {
        return [
            'Authorization' => 'Bearer '.KeycloakClientTestMock::getJwtFor($userId),
        ];
    }

    private function getS3CommandNames(): array
    {
        return array_column($this->s3Commands, 'name');
    }

    private function createUpload(int $size = 1000, string $filename = 'photo.jpg', string $type = 'image/jpeg'): array
    {
        $response = $this->client->request('POST', '/uploads', [
            'headers' => $this->headers(),
            'json' => [
                'filename' => $filename,
                'type' => $type,
                'size' => $size,
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        return $response->toArray();
    }

    /**
     * Inserts an upload row directly, e.g. one already completed.
     */
    private function persistUpload(bool $complete = false, ?int $chunkSize = 5_242_880, int $size = 12_000_000): MultipartUpload
    {
        $em = self::getEntityManager();
        $upload = new MultipartUpload();
        $upload->setFilename('video.mp4');
        $upload->setType('video/mp4');
        $upload->setSize($size);
        if (null !== $chunkSize) {
            $upload->setChunkSize($chunkSize);
        }
        $upload->setPath('tests/'.uniqid().'.mp4');
        $upload->setUploadId('existing-s3-upload');
        $upload->setComplete($complete);
        $em->persist($upload);
        $em->flush();

        return $upload;
    }

    private function findUpload(string $id): ?MultipartUpload
    {
        $em = self::getEntityManager();
        $em->clear();

        return $em->find(MultipartUpload::class, $id);
    }

    public function testCreatingAnUploadReturnsThePlanAndThePresignedUrlOfEveryPart(): void
    {
        $data = $this->createUpload(size: 50_000_000);

        $this->assertMatchesUuid($data['id']);
        $this->assertSame('photo.jpg', $data['filename']);
        $this->assertSame('image/jpeg', $data['type']);
        $this->assertSame(50_000_000, $data['size']);
        $this->assertFalse($data['complete']);
        $this->assertNotEmpty($data['createdAt']);

        // The server decides the part size (at least the S3 minimum of 5 MiB)
        $chunkSize = $data['chunkSize'];
        $this->assertGreaterThanOrEqual(5_242_880, $chunkSize);
        $partCount = (int) ceil(50_000_000 / $chunkSize);
        $this->assertSame(range(1, $partCount), array_map('intval', array_keys($data['urls'])));
        foreach ($data['urls'] as $partNumber => $url) {
            $this->assertStringContainsString('partNumber='.$partNumber.'&', $url);
            $this->assertStringContainsString('uploadId='.self::S3_UPLOAD_ID, $url);
            $this->assertStringContainsString('X-Amz-Signature=', $url);
        }

        // One S3 multipart upload was started, with the file type and extension
        $this->assertSame(['CreateMultipartUpload'], $this->getS3CommandNames());
        $args = $this->s3Commands[0]['args'];
        $this->assertSame('image/jpeg', $args['ContentType']);
        $this->assertStringEndsWith('.jpg', $args['Key']);

        // Internal S3 data is persisted but never exposed
        $upload = $this->findUpload($data['id']);
        $this->assertSame(self::S3_UPLOAD_ID, $upload->getUploadId());
        $this->assertSame($chunkSize, $upload->getChunkSize());
        $this->assertArrayNotHasKey('uploadId', $data);
        $this->assertArrayNotHasKey('path', $data);
    }

    public function testAnEmptyFileStillGetsOnePart(): void
    {
        $data = $this->createUpload(size: 0);

        $this->assertCount(1, $data['urls']);
        $this->assertArrayHasKey(1, $data['urls']);
    }

    public function testTheUrlsAreOnlyReturnedOnCreation(): void
    {
        $data = $this->createUpload();

        $response = $this->client->request('GET', '/uploads/'.$data['id'], [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseIsSuccessful();
        $item = $response->toArray();
        $this->assertSame($data['id'], $item['id']);
        $this->assertSame($data['chunkSize'], $item['chunkSize']);
        $this->assertArrayNotHasKey('urls', $item);
    }

    public function testGettingAnUnknownUploadIs404(): void
    {
        $this->client->request('GET', '/uploads/00000000-0000-4000-8000-000000000000', [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function getInvalidUploads(): iterable
    {
        yield 'blank filename' => [['filename' => '', 'type' => 'image/jpeg', 'size' => 10], 422];
        yield 'blank type' => [['filename' => 'a.jpg', 'type' => '', 'size' => 10], 422];
        yield 'negative size' => [['filename' => 'a.jpg', 'type' => 'image/jpeg', 'size' => -1], 400];
        yield 'size above the maximum object size' => [['filename' => 'a.jpg', 'type' => 'image/jpeg', 'size' => 52_776_558_133_249], 400];
    }

    #[DataProvider('getInvalidUploads')]
    public function testInvalidUploadsAreRejectedBeforeReachingS3(array $payload, int $expectedCode): void
    {
        $this->client->request('POST', '/uploads', [
            'headers' => $this->headers(),
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame($expectedCode);
        $this->assertSame([], $this->s3Commands);
        $this->assertSame(0, self::getEntityManager()->getRepository(MultipartUpload::class)->count([]));
    }

    public function testAnonymousCannotStartAnUpload(): void
    {
        $this->client->request('POST', '/uploads', [
            'json' => [
                'filename' => 'photo.jpg',
                'type' => 'image/jpeg',
                'size' => 10,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame([], $this->s3Commands);
    }

    public function testOnlyAdminsListTheUploads(): void
    {
        $data = $this->createUpload();

        $this->client->request('GET', '/uploads', [
            'headers' => $this->headers(),
        ]);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->client->request('GET', '/uploads', [
            'headers' => $this->headers(KeycloakClientTestMock::ADMIN_UID),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertContains($data['id'], array_column($response->toArray()['member'], 'id'));
    }

    public function testRemainingPartUrlsCanBeRequestedToResumeAnUpload(): void
    {
        $upload = $this->persistUpload(chunkSize: 5_242_880, size: 12_000_000);

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => ['from' => 2],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        // The persisted part size is kept
        $this->assertSame(5_242_880, $data['chunkSize']);
        $this->assertSame(3, $data['partCount']);
        $this->assertSame([2, 3], array_map('intval', array_keys($data['urls'])));
        $this->assertStringContainsString('uploadId=existing-s3-upload', $data['urls'][2]);
        // Signing is local: S3 is not called
        $this->assertSame([], $this->s3Commands);
    }

    public function testAllPartUrlsAreReturnedByDefault(): void
    {
        $upload = $this->persistUpload(chunkSize: 5_242_880, size: 12_000_000);

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([1, 2, 3], array_map('intval', array_keys($response->toArray()['urls'])));
    }

    public function testResumingPastTheLastPartReturnsNoUrl(): void
    {
        $upload = $this->persistUpload(chunkSize: 5_242_880, size: 12_000_000);

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => ['from' => 4],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(3, $data['partCount']);
        $this->assertSame([], $data['urls']);
    }

    public function testUploadsCreatedBeforeTheChunkSizeWasPersistedUseTheCurrentPolicy(): void
    {
        $upload = $this->persistUpload(chunkSize: null, size: 1000);

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(5_242_880, $data['chunkSize']);
        $this->assertSame(1, $data['partCount']);
    }

    public static function getInvalidFromValues(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-3];
        yield 'not a number' => ['abc'];
        yield 'float' => [1.5];
    }

    #[DataProvider('getInvalidFromValues')]
    public function testInvalidFromIsRejected(mixed $from): void
    {
        $upload = $this->persistUpload();

        $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => ['from' => $from],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testNoPartUrlForACompletedUpload(): void
    {
        $upload = $this->persistUpload(complete: true);

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/parts', [
            'headers' => $this->headers(),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('already complete', $response->toArray(false)['description']);
    }

    public static function getPartRoutes(): iterable
    {
        yield 'parts' => ['parts', []];
        yield 'part (deprecated)' => ['part', ['part' => 1]];
    }

    #[DataProvider('getPartRoutes')]
    public function testPartUrlsOfAnUnknownUploadAre404(string $route, array $payload): void
    {
        $this->client->request('POST', '/uploads/00000000-0000-4000-8000-000000000000/'.$route, [
            'headers' => $this->headers(),
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeprecatedSinglePartUrl(): void
    {
        $upload = $this->persistUpload();

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/part', [
            'headers' => $this->headers(),
            'json' => ['part' => 2],
        ]);

        $this->assertResponseIsSuccessful();
        $url = $response->toArray()['url'];
        $this->assertStringContainsString('partNumber=2&', $url);
        $this->assertStringContainsString('uploadId=existing-s3-upload', $url);
    }

    public function testDeprecatedSinglePartUrlRequiresThePartNumber(): void
    {
        $upload = $this->persistUpload();

        $response = $this->client->request('POST', '/uploads/'.$upload->getId().'/part', [
            'headers' => $this->headers(),
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame('Missing part', $response->toArray(false)['description']);
    }

    public function testCancellingAnUploadAbortsItOnS3(): void
    {
        $data = $this->createUpload();
        $this->s3Commands = [];

        $this->client->request('DELETE', '/uploads/'.$data['id'], [
            'headers' => $this->headers(),
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->assertContains('AbortMultipartUpload', $this->getS3CommandNames());
        $abort = $this->s3Commands[array_search('AbortMultipartUpload', $this->getS3CommandNames(), true)]['args'];
        $this->assertSame(self::S3_UPLOAD_ID, $abort['UploadId']);
    }

    public function testACancelledUploadIsRemoved(): void
    {
        $data = $this->createUpload();

        $this->client->request('DELETE', '/uploads/'.$data['id'], [
            'headers' => $this->headers(),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->assertNull($this->findUpload($data['id']));
        $this->client->request('GET', '/uploads/'.$data['id'], [
            'headers' => $this->headers(),
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCancellingIsSilentWhenS3NoLongerKnowsTheUpload(): void
    {
        $data = $this->createUpload();

        /** @var S3Client $s3Client */
        $s3Client = static::getContainer()->get('alchemy_storage.s3_client');
        $s3Client->getHandlerList()->setHandler(function (CommandInterface $command) {
            $this->s3Commands[] = ['name' => $command->getName(), 'args' => $command->toArray()];

            return Create::rejectionFor(new \Aws\S3\Exception\S3Exception('The upload does not exist', $command, [
                'code' => 'NoSuchUpload',
            ]));
        });

        $this->client->request('DELETE', '/uploads/'.$data['id'], [
            'headers' => $this->headers(),
        ]);

        // S3 cleans up incomplete uploads on its own: the error is swallowed
        $this->assertResponseStatusCodeSame(204);
    }
}
