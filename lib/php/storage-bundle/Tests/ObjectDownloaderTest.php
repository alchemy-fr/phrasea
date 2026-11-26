<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Storage\ObjectDownloader;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\LazyOpenStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ObjectDownloaderTest extends TestCase
{
    private const string BUCKET = 'databox';

    private string $dir;

    /**
     * @var array<string, string> object content keyed by S3 key
     */
    private array $objects = [];

    /**
     * @var string[]
     */
    private array $requestedKeys = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/'.uniqid('object-downloader-test');
        mkdir($this->dir);
        $this->objects = [];
        $this->requestedKeys = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testObjectsAreWrittenToTheirDestination(): void
    {
        $this->objects = ['a/1.jpg' => 'one', 'b/2.jpg' => 'two'];
        $downloader = new ObjectDownloader($this->createClient($this->createMemoryHandler()), self::BUCKET);

        $failures = $downloader->downloadMany([
            $this->dir.'/first.jpg' => 'a/1.jpg',
            $this->dir.'/second.jpg' => 'b/2.jpg',
        ], 4);

        $this->assertSame([], $failures);
        $this->assertSame('one', file_get_contents($this->dir.'/first.jpg'));
        $this->assertSame('two', file_get_contents($this->dir.'/second.jpg'));
        $this->assertEqualsCanonicalizing(['a/1.jpg', 'b/2.jpg'], $this->requestedKeys);
        $this->assertSame([$this->dir.'/first.jpg', $this->dir.'/second.jpg'], glob($this->dir.'/*'), 'no temporary file is left');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prefixProvider(): iterable
    {
        yield 'no prefix' => ['', 'a/1.jpg'];
        yield 'prefix without separator' => ['tenant', 'tenant/a/1.jpg'];
        yield 'prefix with separator' => ['tenant/', 'tenant/a/1.jpg'];
    }

    #[DataProvider('prefixProvider')]
    public function testTheKeyIsTheOneOfTheStorageAdapter(string $prefix, string $expectedKey): void
    {
        $this->objects = [$expectedKey => 'content'];
        $downloader = new ObjectDownloader($this->createClient($this->createMemoryHandler()), self::BUCKET, $prefix);

        $this->assertSame([], $downloader->downloadMany([$this->dir.'/file.jpg' => '/a/1.jpg'], 1));
        $this->assertSame([$expectedKey], $this->requestedKeys);
        $this->assertSame('content', file_get_contents($this->dir.'/file.jpg'));
    }

    public function testAFailureDoesNotStopTheOtherDownloads(): void
    {
        $this->objects = ['a/1.jpg' => 'one', 'c/3.jpg' => 'three'];
        $downloader = new ObjectDownloader($this->createClient($this->createMemoryHandler()), self::BUCKET);

        $missing = $this->dir.'/missing.jpg';
        file_put_contents($missing, 'partial');

        $failures = $downloader->downloadMany([
            $this->dir.'/first.jpg' => 'a/1.jpg',
            $missing => 'b/2.jpg',
            $this->dir.'/third.jpg' => 'c/3.jpg',
        ], 1);

        $this->assertSame([$missing], array_keys($failures));
        $this->assertInstanceOf(S3Exception::class, $failures[$missing]);
        $this->assertFileDoesNotExist($missing);
        $this->assertSame('one', file_get_contents($this->dir.'/first.jpg'));
        $this->assertSame('three', file_get_contents($this->dir.'/third.jpg'));
    }

    public function testTheBodyWrittenToTheSinkIsNotCopiedAgain(): void
    {
        $this->objects = ['a/1.jpg' => 'one'];
        // As the Guzzle handler does: the body is the sink file
        $handler = function (CommandInterface $command): PromiseInterface {
            $this->requestedKeys[] = $command['Key'];
            $sink = $command['@http']['sink'];
            file_put_contents($sink, $this->objects[$command['Key']]);
            $body = new LazyOpenStream($sink, 'r');
            $body->seek(0, SEEK_END);

            return Create::promiseFor(new Result(['Body' => $body]));
        };
        $downloader = new ObjectDownloader($this->createClient($handler), self::BUCKET);

        $this->assertSame([], $downloader->downloadMany([$this->dir.'/first.jpg' => 'a/1.jpg'], 1));
        $this->assertSame('one', file_get_contents($this->dir.'/first.jpg'));
    }

    public function testTheConcurrencyIsBounded(): void
    {
        $objects = [];
        for ($i = 0; $i < 10; ++$i) {
            $this->objects['k/'.$i] = 'content '.$i;
            $objects[$this->dir.'/'.$i] = 'k/'.$i;
        }

        $inFlight = 0;
        $maxInFlight = 0;
        $handler = function (CommandInterface $command) use (&$inFlight, &$maxInFlight): PromiseInterface {
            $maxInFlight = max($maxInFlight, ++$inFlight);
            $content = $this->objects[$command['Key']];

            // Settled only when waited for, as a transfer in progress
            $promise = new Promise(static function () use (&$promise, &$inFlight, $content): void {
                --$inFlight;
                $promise->resolve(new Result(['Body' => Utils::streamFor($content)]));
            });

            return $promise;
        };
        $downloader = new ObjectDownloader($this->createClient($handler), self::BUCKET);

        $this->assertSame([], $downloader->downloadMany($objects, 3));
        $this->assertSame(3, $maxInFlight);
        foreach ($objects as $destination => $key) {
            $this->assertSame($this->objects[$key], file_get_contents($destination));
        }
    }

    public function testTheTransferIsBounded(): void
    {
        $this->objects = ['a/1.jpg' => 'one'];
        $options = null;
        $handler = function (CommandInterface $command) use (&$options): PromiseInterface {
            $options = $command['@http'];

            return Create::promiseFor(new Result(['Body' => Utils::streamFor($this->objects[$command['Key']])]));
        };
        $downloader = new ObjectDownloader($this->createClient($handler), self::BUCKET, timeout: 120.0);

        $this->assertSame([], $downloader->downloadMany([$this->dir.'/first.jpg' => 'a/1.jpg'], 1));
        $this->assertSame($this->dir.'/first.jpg', $options['sink']);
        $this->assertSame(10, $options['connect_timeout']);
        $this->assertSame(120.0, $options['timeout']);
        $this->assertSame([
            \CURLOPT_LOW_SPEED_LIMIT => 1,
            \CURLOPT_LOW_SPEED_TIME => 60,
        ], $options['curl']);
    }

    public function testNothingToDownload(): void
    {
        $downloader = new ObjectDownloader($this->createClient($this->createMemoryHandler()), self::BUCKET);

        $this->assertSame([], $downloader->downloadMany([], 8));
        $this->assertSame([], $this->requestedKeys);
    }

    /**
     * Ignores the sink and answers the body in memory, as LocalS3Handler of the databox tests.
     */
    private function createMemoryHandler(): callable
    {
        return function (CommandInterface $command): PromiseInterface {
            $this->assertSame('GetObject', $command->getName());
            $this->assertSame(self::BUCKET, $command['Bucket']);
            $this->requestedKeys[] = $command['Key'];

            if (!isset($this->objects[$command['Key']])) {
                return Create::rejectionFor(new S3Exception('Not Found', $command, ['code' => 'NoSuchKey']));
            }

            return Create::promiseFor(new Result(['Body' => Utils::streamFor($this->objects[$command['Key']])]));
        };
    }

    private function createClient(callable $handler): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => ['key' => 'key', 'secret' => 'secret'],
            'handler' => $handler,
        ]);
    }
}
