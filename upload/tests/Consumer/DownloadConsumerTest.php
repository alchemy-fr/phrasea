<?php

declare(strict_types=1);

namespace App\Tests\Consumer;

use App\Consumer\DownloadConsumer;
use App\Storage\AssetManager;
use App\Storage\FileStorageManager;
use GuzzleHttp\Client;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Log\Test\TestLogger;

class DownloadConsumerTest extends TestCase
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
        $storageStub = $this->createMock(FileStorageManager::class);
        $assetManagerStub = $this->createMock(AssetManager::class);
        $assetManagerStub
            ->expects($this->once())
            ->method('createAsset')
            ->with(
                $this->stringEndsWith('.'.$expectedExtension),
                $expectedMimeType,
                'baz'.($expectedExtension ? '.'.$expectedExtension : ''),
                6,
                'id-test'
            );

        $storageStub
            ->expects($this->once())
            ->method('generatePath')
            ->with(
                $expectedExtension
            )
            ->willReturn('aa/bb/baz.'.$expectedExtension);

        $handler = new MockHandler([
            $response,
        ]);

        $clientStub = $client = new Client(['handler' => $handler]);

        $consumer = new DownloadConsumer($storageStub, $clientStub, $assetManagerStub);

        $logger = new TestLogger();
        $consumer->setLogger($logger);

        $message = new AMQPMessage(json_encode([
            'id' => 'id-test',
            'url' => $url,
        ]));
        $result = $consumer->execute($message);

        $this->assertEquals(ConsumerInterface::MSG_ACK, $result);
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
