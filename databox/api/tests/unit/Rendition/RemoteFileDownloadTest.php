<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rendition;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Alchemy\RenditionFactory\Context\TransformationContextFactory;
use Alchemy\RenditionFactory\Context\TransformationContextInterface;
use Alchemy\RenditionFactory\MimeType\MimeTypeGuesser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The remote files of the renditions (download module, watermarks) come from URLs written in the
 * rendition definitions: their size is bounded.
 */
class RemoteFileDownloadTest extends TestCase
{
    private string $workingDirectory;

    protected function setUp(): void
    {
        $this->workingDirectory = sys_get_temp_dir().'/'.uniqid('remote-file-test');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->workingDirectory);
    }

    public function testRemoteFileWithinTheLimitIsDownloaded(): void
    {
        $context = $this->createContext(new MockResponse(str_repeat('a', 10)), 10);

        $path = $context->getRemoteFile('https://example.com/watermark.png');

        $this->assertSame(10, filesize($path));
    }

    public function testDeclaredSizeAboveTheLimitIsRefused(): void
    {
        $context = $this->createContext(new MockResponse(str_repeat('a', 11), [
            'response_headers' => ['Content-Length' => '11'],
        ]), 10);

        $this->assertDownloadRefused($context);
    }

    public function testContentAboveTheLimitIsCutAndNotCached(): void
    {
        $body = (static function (): \Generator {
            for ($i = 0; $i < 5; ++$i) {
                yield str_repeat('a', 4);
            }
        })();
        $context = $this->createContext(new MockResponse($body), 10);

        $this->assertDownloadRefused($context);
    }

    private function assertDownloadRefused(TransformationContextInterface $context): void
    {
        try {
            $context->getRemoteFile('https://example.com/watermark.png');
            $this->fail('The download should have been refused');
        } catch (DownloadTooLargeException) {
        }

        // A partial file left in the cache would be taken as the remote file by the next renditions
        $this->assertSame([], glob($context->getCacheDir('remote').'/*'));
    }

    private function createContext(MockResponse $response, int $maxSize): TransformationContextInterface
    {
        $factory = new TransformationContextFactory(
            new MimeTypeGuesser(),
            $this->workingDirectory,
            new MockHttpClient($response),
            allowPrivateNetwork: true,
            maxDownloadSize: $maxSize,
        );

        return $factory->create();
    }
}
