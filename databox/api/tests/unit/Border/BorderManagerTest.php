<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\PathGeneratorInterface;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\BorderManager;
use App\Border\Model\InputFile;
use App\Border\UploaderOrigins;
use App\Border\UriDownloader;
use App\Entity\Core\Workspace;
use App\Service\Storage\FilePathGenerator;
use App\Service\Storage\TemporaryFiles;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BorderManagerTest extends TestCase
{
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";

    public function testFileOfARemoteUploaderCannotBeOnThePrivateNetwork(): void
    {
        $this->expectException(TransportException::class);
        $this->createBorderManager()->acceptFile(
            new InputFile('secret.png', 'image/png', 42, 'http://169.254.169.254/latest/meta-data/'),
            new Workspace(),
        );
    }

    public function testFileOfTheUploaderStorageMayBeOnThePrivateNetwork(): void
    {
        $file = $this->createBorderManager()->acceptFile(
            new InputFile('image.png', 'image/png', 42, 'http://10.0.0.6:9000/uploader/image.png?X-Amz-Signature=x'),
            new Workspace(),
        );

        $this->assertSame('image.png', $file->getOriginalName());
        $this->assertSame('image/png', $file->getDetectedType());
    }

    private function createBorderManager(): BorderManager
    {
        $pathGenerator = $this->createStub(PathGeneratorInterface::class);
        $pathGenerator->method('generatePath')->willReturn('files/image.png');

        return new BorderManager(
            $this->createStub(EntityManagerInterface::class),
            new UriDownloader(new MockHttpClient(new MockResponse(self::PNG)), 'prod', new MultipartUploadPlanner(1, 1024, 1024, 1024 * 1024), 900, new TemporaryFiles(sys_get_temp_dir())),
            $this->createStub(FileStorageManager::class),
            new FilePathGenerator($pathGenerator),
            new FileValidator(''),
            new UploaderOrigins('http://10.0.0.5:8080', 'http://10.0.0.6:9000'),
        );
    }
}
