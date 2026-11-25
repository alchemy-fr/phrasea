<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Upload\ContentTypeDetector;
use Alchemy\StorageBundle\Upload\ContentTypeMismatchException;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\ForbiddenContentException;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use Alchemy\StorageBundle\Upload\UploadManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UploadManagerContentTest extends TestCase
{
    private const string BUCKET = 'uploads';
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";
    /**
     * The ASF Header Object GUID, all libmagic needs to name the container.
     */
    private const string ASF = "\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C\xEE\x01\x00\x00\x00\x00\x00\x00\x05\x00\x00\x00\x01\x02";
    private const string HTML = '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>';

    /**
     * @var CommandInterface[]
     */
    private array $commands = [];

    private MockHandler $handler;

    protected function setUp(): void
    {
        $this->commands = [];
        $this->handler = new MockHandler();
    }

    public function testTheHeadOfTheObjectIsReadAndItsTypeReturned(): void
    {
        $this->queueObject(self::PNG);

        $type = $this->createManager()->validateStoredContent('aa/photo.jpg', 'photo.png', 'image/jpeg', strlen(self::PNG));

        $this->assertSame('image/png', $type);
        $this->assertCount(1, $this->commands);
        $this->assertSame('GetObject', $this->commands[0]->getName());
        $this->assertSame('app/aa/photo.jpg', $this->commands[0]['Key']);
        $this->assertSame(sprintf('bytes=0-%d', ContentTypeDetector::HEAD_SIZE - 1), $this->commands[0]['Range']);
    }

    public function testTheDeclaredTypeIsKeptWhenOnlyItsContainerIsKnown(): void
    {
        $this->queueObject("MM\x00\x2a\x00\x00\x00\x08".str_repeat("\x00", 64));

        $type = $this->createManager()->validateStoredContent('aa/photo.dng', 'photo.dng', 'image/x-adobe-dng', 72);

        $this->assertSame('image/x-adobe-dng', $type);
    }

    public function testAWmaDeclaredAsItsAsfContainerIsAudio(): void
    {
        $this->queueObject(self::ASF);

        $type = $this->createManager('audio/*(.wma)')->validateStoredContent('aa/song.wma', 'song.wma', 'video/x-ms-asf', strlen(self::ASF));

        $this->assertSame('audio/x-ms-wma', $type);
    }

    public function testAWmvDeclaredAsItsAsfContainerIsVideo(): void
    {
        $this->queueObject(self::ASF);

        $type = $this->createManager('video/*(.wmv)')->validateStoredContent('aa/clip.wmv', 'clip.wmv', 'video/x-ms-asf', strlen(self::ASF));

        $this->assertSame('video/x-ms-wmv', $type);
    }

    public function testAContentOfAnotherFamilyIsRefused(): void
    {
        $this->queueObject(self::HTML);

        $this->expectException(ContentTypeMismatchException::class);
        $this->createManager()->validateStoredContent('aa/photo.jpg', 'photo.jpg', 'image/jpeg', strlen(self::HTML));
    }

    public function testAForbiddenContentIsRefused(): void
    {
        $content = "#EXTM3U\n#EXTINF:10,\nfile:///etc/passwd\n";
        $this->queueObject($content);

        $this->expectException(ForbiddenContentException::class);
        $this->createManager()->validateStoredContent('aa/video.mp4', 'video.mp4', 'video/mp4', strlen($content));
    }

    public function testTheContentTypeMustBeAllowed(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->queueObject($svg);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "image/svg+xml" with extension "jpg" is not allowed');
        $this->createManager('image/jpeg(.jpg)')->validateStoredContent('aa/photo.jpg', 'photo.jpg', 'image/jpeg', strlen($svg));
    }

    public function testAnEmptyObjectIsNotRead(): void
    {
        $type = $this->createManager()->validateStoredContent('aa/data.bin', 'data.bin', 'application/octet-stream', 0);

        $this->assertSame('application/x-empty', $type);
        $this->assertSame([], $this->commands);
    }

    public function testAnEmptyObjectDeclaredAsAnImageIsRefused(): void
    {
        $this->expectException(ContentTypeMismatchException::class);
        $this->createManager()->validateStoredContent('aa/photo.jpg', 'photo.jpg', 'image/jpeg', 0);
    }

    public function testTheMultipartUploadsAreNeverStoredWithTheDeclaredType(): void
    {
        $this->handler->append(function (CommandInterface $command): Result {
            $this->commands[] = $command;

            return new Result(['UploadId' => 'u1']);
        });

        $this->createManager()->prepareMultipartUpload('aa/page.jpg', 'image/jpeg');

        $this->assertSame('application/octet-stream', $this->commands[0]['ContentType']);
    }

    private function createManager(string $allowedTypes = ''): UploadManager
    {
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => ['key' => 'key', 'secret' => 'secret'],
            'handler' => $this->handler,
        ]);

        return new UploadManager(
            $client,
            self::BUCKET,
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            new FileValidator($allowedTypes),
            'app/',
            new MultipartUploadPlanner(5 * 1024 * 1024, 5 * 1024 * 1024 * 1024, 10000, 5 * 1024 * 1024 * 1024 * 1024),
        );
    }

    private function queueObject(string $content): void
    {
        $this->handler->append(function (CommandInterface $command) use ($content): Result {
            $this->commands[] = $command;

            return new Result(['Body' => Utils::streamFor($content)]);
        });
    }
}
