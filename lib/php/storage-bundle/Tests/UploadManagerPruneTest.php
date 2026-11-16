<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class UploadManagerPruneTest extends TestCase
{
    private const string BUCKET = 'uploads';

    /**
     * @var array<array{name: string, params: array<string, mixed>}>
     */
    private array $commands = [];

    private MockHandler $handler;

    protected function setUp(): void
    {
        $this->commands = [];
        $this->handler = new MockHandler();
    }

    public function testAbortsTheStaleUploadsOfEveryPageUnderThePrefix(): void
    {
        $this->queueList([
            $this->upload('app/aa/old-1.jpg', 'u1', '-2 days'),
            $this->upload('app/aa/recent.jpg', 'u2', '-1 hour'),
        ], nextKeyMarker: 'app/aa/recent.jpg', nextUploadIdMarker: 'u2');
        $this->queueAbort();
        $this->queueList([
            $this->upload('app/bb/old-2.jpg', 'u3', '-30 hours'),
        ]);
        $this->queueAbort();

        $aborted = $this->createManager('app/')->abortStaleUploads(new \DateTimeImmutable('-24 hours'));

        $this->assertSame(2, $aborted);
        $this->assertSame([
            ['name' => 'ListMultipartUploads', 'params' => ['Bucket' => self::BUCKET, 'Prefix' => 'app/']],
            ['name' => 'AbortMultipartUpload', 'params' => ['Bucket' => self::BUCKET, 'Key' => 'app/aa/old-1.jpg', 'UploadId' => 'u1']],
            ['name' => 'ListMultipartUploads', 'params' => [
                'Bucket' => self::BUCKET,
                'Prefix' => 'app/',
                'KeyMarker' => 'app/aa/recent.jpg',
                'UploadIdMarker' => 'u2',
            ]],
            ['name' => 'AbortMultipartUpload', 'params' => ['Bucket' => self::BUCKET, 'Key' => 'app/bb/old-2.jpg', 'UploadId' => 'u3']],
        ], $this->commands);
        $this->assertSame(0, $this->handler->count());
    }

    public function testListsTheWholeBucketWithoutPrefix(): void
    {
        $this->queueList([
            $this->upload('aa/old.jpg', 'u1', '-2 days'),
        ]);
        $this->queueAbort();

        $aborted = $this->createManager('')->abortStaleUploads(new \DateTimeImmutable('-24 hours'));

        $this->assertSame(1, $aborted);
        $this->assertSame(['Bucket' => self::BUCKET], $this->commands[0]['params']);
        $this->assertSame('aa/old.jpg', $this->commands[1]['params']['Key']);
    }

    public function testNoUpload(): void
    {
        $this->queueList([]);

        $this->assertSame(0, $this->createManager('app/')->abortStaleUploads(new \DateTimeImmutable('-24 hours')));
        $this->assertCount(1, $this->commands);
    }

    public function testAnUploadAlreadyGoneIsSkipped(): void
    {
        $this->queueList([
            $this->upload('app/aa/gone.jpg', 'u1', '-2 days'),
            $this->upload('app/aa/old.jpg', 'u2', '-2 days'),
        ]);
        $this->queueAbort('NoSuchUpload');
        $this->queueAbort();

        $aborted = $this->createManager('app/')->abortStaleUploads(new \DateTimeImmutable('-24 hours'));

        $this->assertSame(1, $aborted);
        $this->assertSame(0, $this->handler->count());
    }

    public function testOtherErrorsAreNotSwallowed(): void
    {
        $this->queueList([
            $this->upload('app/aa/old.jpg', 'u1', '-2 days'),
        ]);
        $this->queueAbort('AccessDenied');

        $this->expectException(S3Exception::class);
        $this->createManager('app/')->abortStaleUploads(new \DateTimeImmutable('-24 hours'));
    }

    public function testMinimumAgeCannotBeShorterThanThePartUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->createManager('app/')->pruneParts(UploadManager::PRUNE_MIN_AGE_LIMIT - 1);
    }

    private function createManager(string $pathPrefix): UploadManager
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => ['key' => 'key', 'secret' => 'secret'],
            'handler' => $this->handler,
        ]);

        return new UploadManager(
            $client,
            self::BUCKET,
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            new FileValidator(''),
            $pathPrefix,
            new MultipartUploadPlanner(5 * 1024 * 1024, 5 * 1024 * 1024 * 1024, 10000, 5 * 1024 * 1024 * 1024 * 1024),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(string $key, string $uploadId, string $initiated): array
    {
        return [
            'Key' => $key,
            'UploadId' => $uploadId,
            'Initiated' => new \DateTimeImmutable($initiated),
        ];
    }

    /**
     * @param array<array<string, mixed>> $uploads
     */
    private function queueList(array $uploads, ?string $nextKeyMarker = null, ?string $nextUploadIdMarker = null): void
    {
        $this->handler->append(function (CommandInterface $command) use ($uploads, $nextKeyMarker, $nextUploadIdMarker): Result {
            $this->record($command);

            return new Result(array_filter([
                'Uploads' => $uploads,
                'IsTruncated' => null !== $nextKeyMarker,
                'NextKeyMarker' => $nextKeyMarker,
                'NextUploadIdMarker' => $nextUploadIdMarker,
            ], fn ($value): bool => null !== $value));
        });
    }

    private function queueAbort(?string $errorCode = null): void
    {
        $this->handler->append(function (CommandInterface $command) use ($errorCode): Result|S3Exception {
            $this->record($command);

            if (null !== $errorCode) {
                return new S3Exception($errorCode, $command, ['code' => $errorCode]);
            }

            return new Result([]);
        });
    }

    /**
     * Keeps the parameters set by the code under test, not those the SDK adds.
     */
    private function record(CommandInterface $command): void
    {
        $params = array_intersect_key($command->toArray(), array_flip([
            'Bucket',
            'Prefix',
            'KeyMarker',
            'UploadIdMarker',
            'Key',
            'UploadId',
        ]));

        $this->commands[] = ['name' => $command->getName(), 'params' => $params];
    }
}
