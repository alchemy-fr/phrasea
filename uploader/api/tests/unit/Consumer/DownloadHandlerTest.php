<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Consumer\Handler\Download;
use App\Consumer\Handler\DownloadHandler;
use App\Entity\Asset;
use App\Entity\Target;
use App\Storage\AssetManager;
use App\Validation\CommitValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

class DownloadHandlerTest extends TestCase
{
    private const string JPEG = "\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01";
    private const string GIF = "GIF89a\x01\x00\x01\x00";

    #[DataProvider('downloadProvider')]
    public function testDownload(
        string $url,
        MockResponse $response,
        string $expectedMimeType,
        ?string $expectedExtension,
        int $expectedSize = 6,
    ): void {
        /** @var MessageBusInterface|MockObject $busMock */
        $busMock = $this->createMock(MessageBusInterface::class);
        $busMock
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) {
                return new Envelope($message, []);
            })
        ;

        /** @var FileStorageManager|MockObject $storageStub */
        $storageStub = $this->createStub(FileStorageManager::class);
        /** @var PathGeneratorInterface|MockObject $pathGeneratorStub */
        $pathGeneratorStub = $this->createMock(PathGeneratorInterface::class);
        /** @var AssetManager|MockObject $assetManagerStub */
        $assetManagerStub = $this->createMock(AssetManager::class);
        $assetManagerStub
            ->expects($this->once())
            ->method('createAsset')
            ->with(
                $this->isInstanceOf(Target::class),
                $this->stringEndsWith('.'.$expectedExtension),
                $expectedMimeType,
                'baz'.($expectedExtension ? '.'.$expectedExtension : ''),
                $expectedSize
            )
            ->willReturn(new Asset())
        ;

        $pathGeneratorStub
            ->expects($this->once())
            ->method('generatePath')
            ->with(
                $expectedExtension
            )
            ->willReturn('aa/bb/baz.'.$expectedExtension);

        $clientStub = new MockHttpClient([
            $response,
        ]);

        /** @var EntityManagerInterface|MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')
            ->with(Target::class, 'c705d014-5e18-4711-bad6-5e9e27e10099')
            ->willReturn(new Target());

        $consumer = self::createHandler($storageStub, $clientStub, $assetManagerStub, $busMock, $pathGeneratorStub, $em);

        $consumer(self::createMessage($url));
    }

    private static function createHandler(
        FileStorageManager $storage,
        MockHttpClient $client,
        AssetManager $assetManager,
        MessageBusInterface $bus,
        PathGeneratorInterface $pathGenerator,
        EntityManagerInterface $em,
        ?int $maxFileSize = null,
        ?int $maxCommitSize = null,
        ?int $maxFileCount = null,
        int $maxObjectSize = 52_776_558_133_248,
        string $allowedFileTypes = '',
        ?LoggerInterface $logger = null,
        float $maxDuration = 600,
    ): DownloadHandler {
        return new DownloadHandler(
            $storage,
            $client,
            $assetManager,
            $bus,
            $pathGenerator,
            $em,
            new CommitValidator($maxCommitSize, $maxFileCount),
            new MultipartUploadPlanner(5_242_880, 5_368_709_120, 10_000, $maxObjectSize),
            new FileValidator($allowedFileTypes),
            $logger ?? new NullLogger(),
            $maxFileSize,
            $maxDuration,
        );
    }

    private static function createMessage(string $url = 'http://foo.bar/baz.jpg'): Download
    {
        return new Download(
            $url,
            'USER_ID',
            'c705d014-5e18-4711-bad6-5e9e27e10099',
            'en',
            null,
            ['foo' => 'bar'],
        );
    }

    private function createEm(): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn(new Target());

        return $em;
    }

    public function testTheFileIsStreamedToTheStorage(): void
    {
        $stored = null;
        $storage = $this->createMock(FileStorageManager::class);
        $storage->expects($this->never())->method('store');
        $storage->expects($this->once())
            ->method('storeStream')
            ->willReturnCallback(function (string $path, $stream) use (&$stored): void {
                $stored = stream_get_contents($stream);
            });

        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->once())
            ->method('createAsset')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), strlen(self::JPEG) + 12)
            ->willReturn(new Asset());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $handler = self::createHandler(
            $storage,
            new MockHttpClient([new MockResponse([self::JPEG, 'foo', 'bar', 'bazbaz'], ['response_headers' => ['content-type' => 'image/jpeg']])]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
        );

        $handler(self::createMessage());

        $this->assertSame(self::JPEG.'foobarbazbaz', $stored);
    }

    public static function refusedContentProvider(): iterable
    {
        yield 'html served as an image' => ['<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', ['content-type' => 'image/jpeg'], ''];
        yield 'hls playlist' => ["#EXTM3U\n#EXTINF:10,\nfile:///etc/passwd\n", ['content-type' => 'video/mp4'], ''];
        yield 'type not allowed' => [self::GIF, ['content-type' => 'image/gif'], 'image/jpeg(.jpg)'];
        yield 'type not allowed, without declared type' => [self::GIF, [], 'image/jpeg(.jpg)'];
    }

    #[DataProvider('refusedContentProvider')]
    public function testARefusedContentIsNotStored(string $content, array $headers, string $allowedFileTypes): void
    {
        $logger = self::createLogger();
        $storage = $this->createMock(FileStorageManager::class);
        $storage->expects($this->never())->method('storeStream');
        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->never())->method('createAsset');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = self::createHandler(
            $storage,
            new MockHttpClient([new MockResponse($content, ['response_headers' => $headers])]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
            allowedFileTypes: $allowedFileTypes,
            logger: $logger,
        );

        try {
            $handler(self::createMessage());
            $this->fail('The download must be refused');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringStartsWith('Download refused', $e->getMessage());
        }

        // The URL comes from the user: it is never interpolated in the message
        $this->assertCount(1, $logger->records);
        $this->assertStringNotContainsString('foo.bar', $logger->records[0]['message']);
        $this->assertSame(['url' => 'http://foo.bar/baz.jpg'], $logger->records[0]['context']);
    }

    private static function createLogger(): RecordingLogger
    {
        return new RecordingLogger();
    }

    public function testTheDownloadIsBoundedInDuration(): void
    {
        $assetManager = $this->createStub(AssetManager::class);
        $assetManager->method('createAsset')->willReturn(new Asset());
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $options = null;
        $handler = self::createHandler(
            $this->createStub(FileStorageManager::class),
            new MockHttpClient(function (string $method, string $url, array $o) use (&$options): MockResponse {
                $options = $o;

                return new MockResponse(self::JPEG, ['response_headers' => ['content-type' => 'image/jpeg']]);
            }),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
            maxDuration: 42,
        );

        $handler(self::createMessage());

        $this->assertSame(42.0, $options['max_duration'] ?? null);
    }

    public function testADownloadThatTookTooLongIsNotRetried(): void
    {
        $storage = $this->createMock(FileStorageManager::class);
        $storage->expects($this->never())->method('storeStream');
        $logger = self::createLogger();

        $handler = self::createHandler(
            $storage,
            new MockHttpClient([new MockResponse((static function (): \Generator {
                yield self::JPEG;
                usleep(30_000);
                // What the client throws once max_duration is reached
                yield new TransportException('Max duration was reached');
            })())]),
            $this->createStub(AssetManager::class),
            $this->createStub(MessageBusInterface::class),
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
            logger: $logger,
            maxDuration: 0.01,
        );

        try {
            $handler(self::createMessage());
            $this->fail('The download must be aborted');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertStringContainsString('took more than', $e->getMessage());
            $this->assertInstanceOf(TransportException::class, $e->getPrevious());
        }
        $this->assertSame(['url' => 'http://foo.bar/baz.jpg'], $logger->records[0]['context'] ?? null);
    }

    public function testATransportErrorBeforeTheMaximumDurationIsRetried(): void
    {
        $handler = self::createHandler(
            $this->createStub(FileStorageManager::class),
            new MockHttpClient([new MockResponse((static function (): \Generator {
                yield self::JPEG;
                yield new TransportException('Connection reset');
            })())]),
            $this->createStub(AssetManager::class),
            $this->createStub(MessageBusInterface::class),
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
        );

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection reset');
        $handler(self::createMessage());
    }

    public static function contentDispositionProvider(): iterable
    {
        yield 'none' => [null, null];
        yield 'without file name' => ['inline', null];
        yield 'quoted' => ['attachment; filename="photo.jpg"', 'photo.jpg'];
        yield 'not quoted' => ['attachment; filename=photo.jpg', 'photo.jpg'];
        yield 'with a path' => ['attachment; filename="../../etc/cron.d/x"', 'x'];
        yield 'extended, preferred' => ["attachment; filename=\"photo.jpg\"; filename*=UTF-8''caf%C3%A9.jpg", 'café.jpg'];
        yield 'extended, latin-1' => ["attachment; filename*=ISO-8859-1''caf%E9.jpg", 'café.jpg'];
        yield 'extended, encoded line break' => ["attachment; filename*=UTF-8''a%0D%0Ab.jpg", 'ab.jpg'];
        yield 'empty' => ['attachment; filename=""', null];
    }

    #[DataProvider('contentDispositionProvider')]
    public function testTheFileNameOfTheContentDisposition(?string $header, ?string $expected): void
    {
        $this->assertSame($expected, DownloadHandler::getFileNameFromContentDisposition($header));
    }

    public static function urlFileNameProvider(): iterable
    {
        yield 'file' => ['http://foo.bar/a/baz.jpg', 'baz.jpg'];
        yield 'query and fragment' => ['http://foo.bar/baz.jpg?x=/y.png#z', 'baz.jpg'];
        yield 'encoded' => ['http://foo.bar/my%20photo.jpg', 'my photo.jpg'];
        yield 'encoded separator' => ['http://foo.bar/a%2F..%2Fb.jpg', 'b.jpg'];
        yield 'root' => ['http://foo.bar/', 'download'];
        yield 'host only' => ['http://foo.bar', 'download'];
    }

    #[DataProvider('urlFileNameProvider')]
    public function testTheFileNameOfTheUrl(string $url, string $expected): void
    {
        $this->assertSame($expected, DownloadHandler::getFileNameFromUrl($url));
    }

    public function testAnUnknownContentTypeGetsAGenericExtension(): void
    {
        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->once())
            ->method('createAsset')
            ->with($this->anything(), $this->anything(), 'image/x-phrasea-unknown', 'baz.bin')
            ->willReturn(new Asset());
        $pathGenerator = $this->createMock(PathGeneratorInterface::class);
        $pathGenerator->expects($this->once())->method('generatePath')->with('bin')->willReturn('aa/bb/baz.bin');
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $handler = self::createHandler(
            $this->createStub(FileStorageManager::class),
            new MockHttpClient([new MockResponse(self::JPEG, ['response_headers' => ['content-type' => 'image/x-phrasea-unknown']])]),
            $assetManager,
            $bus,
            $pathGenerator,
            $this->createEm(),
        );

        $handler(self::createMessage('http://foo.bar/baz'));
    }

    public function testTheFileNameIsReadFromTheContentDisposition(): void
    {
        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->once())
            ->method('createAsset')
            ->with($this->anything(), $this->anything(), 'image/jpeg', 'photo.jpg')
            ->willReturn(new Asset());
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $handler = self::createHandler(
            $this->createStub(FileStorageManager::class),
            new MockHttpClient([new MockResponse(self::JPEG, ['response_headers' => [
                'content-type' => 'image/jpeg',
                'content-disposition' => 'attachment; filename="photo.jpg"',
            ]])]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
        );

        $handler(self::createMessage('http://foo.bar/download?id=1'));
    }

    public static function oversizedResponseProvider(): iterable
    {
        yield 'max_upload_file_size, announced' => [new MockResponse('0123456789', ['response_headers' => ['content-length' => '10']]), 9, 100];
        yield 'max_upload_file_size, not announced' => [new MockResponse(['01234', '56789']), 9, 100];
        yield 'maximum object size when no max_upload_file_size' => [new MockResponse(['01234', '56789']), null, 9];
    }

    #[DataProvider('oversizedResponseProvider')]
    public function testADownloadAboveTheMaximumSizeIsAborted(MockResponse $response, ?int $maxFileSize, int $maxObjectSize): void
    {
        $storage = $this->createMock(FileStorageManager::class);
        $storage->expects($this->never())->method('storeStream');
        $storage->expects($this->never())->method('store');
        $assetManager = $this->createMock(AssetManager::class);
        $assetManager->expects($this->never())->method('createAsset');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = self::createHandler(
            $storage,
            new MockHttpClient([$response]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
            maxFileSize: $maxFileSize,
            maxObjectSize: $maxObjectSize,
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(self::createMessage());
    }

    public static function commitLimitProvider(): iterable
    {
        yield 'max_upload_commit_size' => [5, null];
        yield 'max_upload_file_count' => [null, 0];
    }

    #[DataProvider('commitLimitProvider')]
    public function testACommitAboveTheLimitsIsNotDispatched(?int $maxCommitSize, ?int $maxFileCount): void
    {
        $asset = new Asset();
        $assetManager = $this->createStub(AssetManager::class);
        $assetManager->method('createAsset')->willReturn($asset);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(new Target());
        // The asset is removed with its file
        $em->expects($this->once())->method('remove')->with($asset);

        $handler = self::createHandler(
            $this->createStub(FileStorageManager::class),
            new MockHttpClient([new MockResponse('foobar')]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $em,
            maxCommitSize: $maxCommitSize,
            maxFileCount: $maxFileCount,
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler(self::createMessage());
    }

    public static function downloadProvider(): array
    {
        return [
            [
                'http://foo.bar/baz.jpg',
                new MockResponse(self::JPEG, ['response_headers' => ['content-type' => 'image/jpeg']]),
                'image/jpeg',
                'jpg',
                strlen(self::JPEG),
            ],
            [
                'http://foo.bar/baz.jpg',
                new MockResponse(self::GIF, ['response_headers' => ['content-type' => 'image/gif']]),
                'image/gif',
                'jpg',
                strlen(self::GIF),
            ],
            [
                'http://foo.bar/baz',
                new MockResponse(self::GIF, ['response_headers' => ['content-type' => 'image/gif']]),
                'image/gif',
                'gif',
                strlen(self::GIF),
            ],
            [
                'http://foo.bar/baz.txt?foo=bar',
                new MockResponse('foobar', []),
                'application/octet-stream',
                'txt',
            ],
            [
                'http://foo.bar/baz',
                new MockResponse('foobar', []),
                'application/octet-stream',
                null,
            ],
        ];
    }
}
