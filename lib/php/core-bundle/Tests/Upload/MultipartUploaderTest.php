<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Upload;

use Alchemy\CoreBundle\Upload\MultipartUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MultipartUploaderTest extends TestCase
{
    private const CHUNK = 10;

    /** @var list<string> */
    private array $apiCalls = [];
    /** @var list<array{url: string, body: string}> */
    private array $puts = [];
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'multipart');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    private function writeFile(string $content): void
    {
        file_put_contents($this->file, $content);
    }

    private static function urls(int $from, int $partCount, string $version = 'v1'): array
    {
        $urls = [];
        for ($n = $from; $n <= $partCount; ++$n) {
            $urls[(string) $n] = sprintf('https://s3.test/part-%d?sig=%s', $n, $version);
        }

        return $urls;
    }

    private function createApiClient(int $size): MockHttpClient
    {
        $partCount = max(1, (int) ceil($size / self::CHUNK));

        return new MockHttpClient(function (string $method, string $url, array $options) use ($partCount): ResponseInterface {
            $path = parse_url($url, PHP_URL_PATH);
            $this->apiCalls[] = $method.' '.$path.(isset($options['body']) && is_string($options['body']) && '' !== $options['body'] ? ' '.$options['body'] : '');

            return match (true) {
                'POST' === $method && '/uploads' === $path => new MockResponse(json_encode([
                    'id' => 'up-1',
                    'chunkSize' => self::CHUNK,
                    'urls' => self::urls(1, $partCount),
                ])),
                'POST' === $method && '/uploads/up-1/parts' === $path => new MockResponse(json_encode([
                    'chunkSize' => self::CHUNK,
                    'partCount' => $partCount,
                    'urls' => self::urls(json_decode($options['body'], true)['from'], $partCount, 'v2'),
                ])),
                'DELETE' === $method => new MockResponse('', ['http_code' => 204]),
                default => throw new \LogicException(sprintf('Unexpected %s %s', $method, $url)),
            };
        }, 'https://api.test');
    }

    /**
     * @param \Closure(string $url, int $call): ?int $failWith HTTP status to answer instead of 200
     */
    private function createStorageClient(?\Closure $failWith = null): MockHttpClient
    {
        $call = 0;

        return new MockHttpClient(function (string $method, string $url, array $options) use ($failWith, &$call): MockResponse {
            $body = $options['body'];
            if ($body instanceof \Closure) {
                $content = '';
                while ('' !== $chunk = $body(4)) {
                    $content .= $chunk;
                }
                $body = $content;
            }
            $this->puts[] = ['url' => $url, 'body' => $body];

            $status = $failWith?->__invoke($url, $call++);
            if (null !== $status) {
                return new MockResponse('', ['http_code' => $status]);
            }
            preg_match('#part-(\d+)#', $url, $m);

            return new MockResponse('', ['response_headers' => ['ETag: "etag-'.$m[1].'"']]);
        });
    }

    public function testUploadsEveryPartWithTheUrlsReturnedAtCreation(): void
    {
        $this->writeFile('0123456789abcdefghijKLMNO');
        $uploader = new MultipartUploader($this->createStorageClient());

        $result = $uploader->upload($this->createApiClient(25), $this->file, 'file.bin', 'application/octet-stream');

        $this->assertSame([
            'uploadId' => 'up-1',
            'parts' => [
                ['PartNumber' => 1, 'ETag' => '"etag-1"'],
                ['PartNumber' => 2, 'ETag' => '"etag-2"'],
                ['PartNumber' => 3, 'ETag' => '"etag-3"'],
            ],
        ], $result);
        $this->assertSame(['POST /uploads {"filename":"file.bin","type":"application\/octet-stream","size":25}'], $this->apiCalls);
        $this->assertSame(['0123456789', 'abcdefghij', 'KLMNO'], array_column($this->puts, 'body'));
    }

    public function testEmptyFileIsUploadedAsASingleEmptyPart(): void
    {
        $this->writeFile('');
        $uploader = new MultipartUploader($this->createStorageClient());

        $result = $uploader->upload($this->createApiClient(0), $this->file, 'empty.txt', 'text/plain');

        $this->assertSame([['PartNumber' => 1, 'ETag' => '"etag-1"']], $result['parts']);
        $this->assertSame([''], array_column($this->puts, 'body'));
    }

    public function testExpiredUrlsAreRenewedFromTheFailingPart(): void
    {
        $this->writeFile(str_repeat('x', 25));
        $uploader = new MultipartUploader($this->createStorageClient(
            fn (string $url): ?int => str_contains($url, 'part-2?sig=v1') ? 403 : null,
        ));

        $result = $uploader->upload($this->createApiClient(25), $this->file, 'file.bin', null);

        $this->assertSame([1, 2, 3], array_column($result['parts'], 'PartNumber'));
        $this->assertContains('POST /uploads/up-1/parts {"from":2}', $this->apiCalls);
        $this->assertSame([
            'https://s3.test/part-1?sig=v1',
            'https://s3.test/part-2?sig=v1',
            'https://s3.test/part-2?sig=v2',
            'https://s3.test/part-3?sig=v2',
        ], array_column($this->puts, 'url'));
        // The retried part is re-sent from its start
        $this->assertSame('xxxxxxxxxx', $this->puts[2]['body']);
    }

    public function testServerErrorsAreRetried(): void
    {
        $this->writeFile('0123456789abc');
        $uploader = new MultipartUploader($this->createStorageClient(
            fn (string $url, int $call): ?int => 0 === $call ? 503 : null,
        ));

        $result = $uploader->upload($this->createApiClient(13), $this->file, 'file.bin', null);

        $this->assertCount(2, $result['parts']);
        $this->assertSame(['0123456789', '0123456789', 'abc'], array_column($this->puts, 'body'));
    }

    public function testUploadIsCancelledWhenAPartCannotBeSent(): void
    {
        $this->writeFile(str_repeat('x', 25));
        $uploader = new MultipartUploader($this->createStorageClient(
            fn (string $url): ?int => str_contains($url, 'part-2') ? 400 : null,
        ));

        try {
            $uploader->upload($this->createApiClient(25), $this->file, 'file.bin', null);
            $this->fail('The upload should have failed');
        } catch (ClientExceptionInterface $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }

        $this->assertSame('DELETE /uploads/up-1', end($this->apiCalls));
        $this->assertCount(2, $this->puts, 'Client errors are not retried');
    }
}
