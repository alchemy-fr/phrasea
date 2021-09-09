<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use Alchemy\StorageBundle\Storage\PathGenerator;
use App\Consumer\Handler\DownloadHandler;
use App\Entity\Asset;
use App\Storage\AssetManager;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class DownloadHandlerTest extends TestCase
{
    /**
     * @dataProvider downloadProvider
     */
    public function testDownload(
        string $url,
        Response $response,
        string $expectedMimeType,
        ?string $expectedExtension
    ): void {
        /** @var EventProducer|MockObject $producerStub */
        $producerStub = $this->createMock(EventProducer::class);
        $producerStub
            ->expects($this->once())
            ->method('publish')
            ->with(
                $this->callback(function ($subject) {
                    return $subject instanceof EventMessage
                        && is_string($subject->getPayload()['user_id']);
                })
            );

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

        $handler = new MockHandler([
            $response,
        ]);

        $clientStub = new Client(['handler' => $handler]);

        /** @var EntityManagerInterface|MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $consumer = new DownloadHandler(
            $storageStub,
            $clientStub,
            $assetManagerStub,
            $producerStub,
            $pathGeneratorStub
        );
        $consumer->setEntityManager($em);

        $logger = new TestLogger();
        $consumer->setLogger($logger);

        $message = new EventMessage($consumer::EVENT, [
            'url' => $url,
            'user_id' => 'USER_ID',
            'form_data' => ['foo' => 'bar'],
            'locale' => 'en',
        ]);
        $consumer->handle($message);
    }

    public function downloadProvider(): array
    {
        return [
            [
                'http://foo.bar/baz.jpg',
                new Response(200, ['Content-Type' => 'image/jpeg'], 'foobar'),
                'image/jpeg',
                'jpg',
            ],
            [
                'http://foo.bar/baz.jpg',
                new Response(200, ['Content-Type' => 'image/gif'], 'foobar'),
                'image/gif',
                'jpg',
            ],
            [
                'http://foo.bar/baz',
                new Response(200, ['Content-Type' => 'image/gif'], 'foobar'),
                'image/gif',
                'gif',
            ],
            [
                'http://foo.bar/baz.txt?foo=bar',
                new Response(200, [], 'foobar'),
                'application/octet-stream',
                'txt',
            ],
            [
                'http://foo.bar/baz',
                new Response(200, [], 'foobar'),
                'application/octet-stream',
                null,
            ],
        ];
    }
}
