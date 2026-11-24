<?php

declare(strict_types=1);

namespace App\Tests\Unit\Configurator\Vendor\S3;

use App\Configurator\Vendor\S3\S3Configurator;
use App\Configurator\Vendor\S3\S3Manager;
use App\Service\ServiceWaiter;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class S3ConfiguratorTest extends TestCase
{
    private const array ENV = [
        'S3_ENDPOINT' => null,
        'S3_INTERNAL_URL' => null,
        'S3_PATH_PREFIX' => null,
        'CONFIGURATOR_S3_BUCKET_NAME' => 'static',
        'DATABOX_S3_BUCKET_NAME' => 'databox',
        'EXPOSE_S3_BUCKET_NAME' => 'expose',
        'UPLOADER_S3_BUCKET_NAME' => 'uploader-deposit',
        'INDEXER_BUCKET_NAME' => 'indexer',
    ];

    /**
     * @var array<string, string|false>
     */
    private array $previousEnv = [];

    /**
     * S3 calls made by the configurator, as "<operation> <bucket>".
     *
     * @var list<string>
     */
    private array $calls = [];

    /**
     * Stands for the S3 endpoint, whose port the configurator waits for.
     *
     * @var resource
     */
    private $endpointSocket;

    protected function setUp(): void
    {
        foreach (self::ENV as $name => $value) {
            $this->previousEnv[$name] = getenv($name);
            $this->setEnv($name, $value);
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $this->endpointSocket = $socket;
        $this->setEnv('S3_ENDPOINT', 'http://'.stream_socket_get_name($socket, false));
    }

    protected function tearDown(): void
    {
        fclose($this->endpointSocket);
        foreach ($this->previousEnv as $name => $value) {
            $this->setEnv($name, false === $value ? null : $value);
        }
    }

    public function testDedicatedStaticBucketIsMadePublic(): void
    {
        $this->createConfigurator()->configure(new NullOutput(), []);

        $this->assertSame([
            'CreateBucket databox',
            'CreateBucket expose',
            'CreateBucket uploader-deposit',
            'CreateBucket static',
            'PutBucketPolicy static',
            'CreateBucket indexer',
        ], $this->calls);
    }

    #[DataProvider('getApplicationBuckets')]
    public function testStaticBucketSharedWithAnApplicationIsRefused(string $variable): void
    {
        $this->setEnv('CONFIGURATOR_S3_BUCKET_NAME', self::ENV[$variable]);

        try {
            $this->createConfigurator()->configure(new NullOutput(), []);
            $this->fail('A static bucket shared with an application must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($variable, $e->getMessage());
        }

        $this->assertSame([], $this->calls);
    }

    public static function getApplicationBuckets(): array
    {
        return [
            'databox' => ['DATABOX_S3_BUCKET_NAME'],
            'expose' => ['EXPOSE_S3_BUCKET_NAME'],
            'uploader' => ['UPLOADER_S3_BUCKET_NAME'],
            'indexer' => ['INDEXER_BUCKET_NAME'],
        ];
    }

    private function createConfigurator(): S3Configurator
    {
        $client = new S3Client([
            'region' => 'eu-west-3',
            'version' => 'latest',
            'credentials' => false,
            'handler' => function (CommandInterface $command) {
                $this->calls[] = sprintf('%s %s', $command->getName(), $command['Bucket']);

                return Create::promiseFor(new Result());
            },
        ]);

        return new S3Configurator(
            new S3Manager($client, new ServiceWaiter(new MockHttpClient(static fn (): MockResponse => new MockResponse()))),
            ['databox', 'expose', 'uploader'],
        );
    }

    private function setEnv(string $name, ?string $value): void
    {
        putenv(null === $value ? $name : $name.'='.$value);
    }
}
