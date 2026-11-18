<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Core\FaceRecognition;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Integration\Core\FaceRecognition\FaceRecognitionClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class FaceRecognitionClientTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'face');
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

            return new JsonMockResponse(['model' => 'test', 'dim' => 1, 'width' => 1, 'height' => 1, 'faces' => []]);
        }, 'http://face-recognition');

        $result = new FaceRecognitionClient($client, new ClientExceptionListener())->detectFaces($this->path);

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
        $this->assertSame(1, preg_match('#multipart/form-data; boundary=(\S+)#', $contentType[0], $matches));
        $this->assertStringContainsString('--'.$matches[1], $sent['body']);
    }
}
