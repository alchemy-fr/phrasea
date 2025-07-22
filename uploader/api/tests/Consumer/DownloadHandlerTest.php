<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Consumer\Handler\Download;
use App\Consumer\Handler\DownloadHandler;
use App\Entity\Asset;
use App\Entity\Target;
use App\Storage\AssetManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DownloadHandlerTest extends TestCase
{
    /**
     * @dataProvider downloadProvider
     */
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
        $storageStub = $this->createMock(FileStorageManager::class);
        /** @var PathGenerator|MockObject $pathGeneratorStub */
        $pathGeneratorStub = $this->createMock(PathGenerator::class);
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

        $consumer = new DownloadHandler(
            $storageStub,
            $clientStub,
            $assetManagerStub,
            $busMock,
            $pathGeneratorStub,
            $em
        );

        $message = new Download(
            $url,
            'USER_ID',
            'c705d014-5e18-4711-bad6-5e9e27e10099',
            'en',
            null,
            ['foo' => 'bar'],
        );
        $consumer($message);
    }

    public function downloadProvider(): array
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
