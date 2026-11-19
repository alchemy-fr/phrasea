<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Vector;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Service\Vector\EmbedderClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class EmbedderClientTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'embed');
        file_put_contents($this->path, 'image-bytes');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testTheImageIsSentAsAStringMultipartBody(): void
    {
        $sent = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): JsonMockResponse {
            $sent = $options;

            return new JsonMockResponse(['vector' => [0.1], 'model' => 'test', 'dim' => 1]);
        }, 'http://embedder');

        $result = new EmbedderClient($client, new ClientExceptionListener())->embedImageFile($this->path);

        $this->assertSame('test', $result['model']);
        // A string body is what lets curl send the request again on a fresh connection
        $this->assertIsString($sent['body']);
        $this->assertStringContainsString('name="file"', $sent['body']);
        $this->assertStringContainsString('image-bytes', $sent['body']);

        $contentType = array_values(array_filter(
            $sent['headers'],
            static fn (string $header): bool => str_starts_with(strtolower($header), 'content-type:'),
        ));
        $this->assertCount(1, $contentType);
        $this->assertMatchesRegularExpression('#multipart/form-data; boundary=#', $contentType[0]);
        $this->assertSame(1, preg_match('#boundary=(\S+)#', $contentType[0], $matches));
        $this->assertStringContainsString('--'.$matches[1], $sent['body']);
    }
}
