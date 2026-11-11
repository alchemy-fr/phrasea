<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
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
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

class DownloadHandlerTest extends TestCase
{
    #[DataProvider('downloadProvider')]
    public function testDownload(
        string $url,
        MockResponse $response,
        string $expectedMimeType,
        ?string $expectedExtension,
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
                6
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
            new NullLogger(),
            $maxFileSize,
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
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), 12)
            ->willReturn(new Asset());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $handler = self::createHandler(
            $storage,
            new MockHttpClient([new MockResponse(['foo', 'bar', 'bazbaz'], ['response_headers' => ['content-type' => 'image/jpeg']])]),
            $assetManager,
            $bus,
            $this->createStub(PathGeneratorInterface::class),
            $this->createEm(),
        );

        $handler(self::createMessage());

        $this->assertSame('foobarbazbaz', $stored);
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
                new MockResponse('foobar', ['response_headers' => ['content-type' => 'image/jpeg']]),
                'image/jpeg',
                'jpg',
            ],
            [
                'http://foo.bar/baz.jpg',
                new MockResponse('foobar', ['response_headers' => ['content-type' => 'image/gif']]),
                'image/gif',
                'jpg',
            ],
            [
                'http://foo.bar/baz',
                new MockResponse('foobar', ['response_headers' => ['content-type' => 'image/gif']]),
                'image/gif',
                'gif',
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
