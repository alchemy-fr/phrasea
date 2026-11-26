<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Upload\ContentTypeMismatchException;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\ForbiddenContentException;
use Alchemy\StorageBundle\Upload\ValidatedContentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileValidatorTest extends TestCase
{
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";
    /**
     * The ASF Header Object GUID, all libmagic needs to name the container.
     */
    private const string ASF = "\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C\xEE\x01\x00\x00\x00\x00\x00\x00\x05\x00\x00\x00\x01\x02";
    private const string ALLOWED_TYPES = 'image/*(.jpg,.jpeg,.png,.svg),application/*(.pdf,.doc),video/*(.mp4,.mov),audio/*(.mp3,.wav),application/x-indesign(.indd),text/csv';

    #[DataProvider('getAcceptedFiles')]
    public function testAcceptedCouple(string $path, ?string $type): void
    {
        $this->expectNotToPerformAssertions();

        (new FileValidator(self::ALLOWED_TYPES))->validateFile($path, $type);
    }

    public static function getAcceptedFiles(): array
    {
        return [
            ['a/b/foo.jpg', 'image/jpeg'],
            ['foo.JPG', 'IMAGE/JPEG'],
            ['foo.png', 'image/png; charset=binary'],
            ['foo.svg', 'image/svg+xml'],
            ['foo.pdf', 'application/pdf'],
            ['foo.mp4', 'video/mp4'],
            ['foo.mp3', 'audio/mpeg'],
            ['foo.indd', 'application/x-indesign'],
            'type guessed from the extension' => ['foo.jpg', null],
            'type without extensions, own extension' => ['foo.csv', 'text/csv'],
        ];
    }

    #[DataProvider('getRefusedFiles')]
    public function testRefusedCouple(string $path, ?string $type): void
    {
        $this->expectException(BadRequestHttpException::class);

        (new FileValidator(self::ALLOWED_TYPES))->validateFile($path, $type);
    }

    public static function getRefusedFiles(): array
    {
        return [
            'html declared with an image extension' => ['foo.jpg', 'text/html'],
            'image with an extension of another type' => ['foo.pdf', 'image/jpeg'],
            'video extension declared as audio' => ['foo.mp4', 'audio/mpeg'],
            'html extension' => ['foo.html', 'image/jpeg'],
            'unlisted type' => ['foo.zip', 'application/zip'],
            'no extension' => ['foo', 'image/jpeg'],
            'invalid type' => ['foo.jpg', 'jpeg'],
            'type without extensions, foreign extension' => ['foo.jpg', 'text/csv'],
            'indd with another type' => ['foo.indd', 'text/html'],
            'unknown extension without type' => ['foo.xyz', null],
        ];
    }

    #[DataProvider('getWildcardTypes')]
    public function testWildcardTypeIsRefused(string $allowedTypes): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FileValidator($allowedTypes))->validateFile('foo.indd', 'application/x-indesign');
    }

    public static function getWildcardTypes(): array
    {
        return [
            ['*/*(.indd)'],
            ['image/*(.jpg),*/*'],
            ['*/jpeg(.jpg)'],
        ];
    }

    public function testEmptyConfigurationAcceptsEverything(): void
    {
        $this->expectNotToPerformAssertions();

        (new FileValidator(''))->validateFile('foo.html', 'text/html');
    }

    public function testMissingSeparatorBetweenTypes(): void
    {
        $validator = new FileValidator('video/*(.mp4)audio/*(.wav)');
        $validator->validateFile('foo.wav', 'audio/wav');

        $this->expectException(BadRequestHttpException::class);
        $validator->validateFile('foo.wav', 'video/mp4');
    }

    public function testValidContent(): void
    {
        $this->assertEquals(new ValidatedContentType('image/png', 'image/png'), (new FileValidator(self::ALLOWED_TYPES))->validateHead(self::PNG, 'foo.png', 'image/png'));
        // The type of the content is checked, with the file name
        $this->assertEquals(new ValidatedContentType('image/png', 'image/png'), (new FileValidator('image/png(.jpg)'))->validateHead(self::PNG, 'foo.jpg', 'image/jpeg'));
    }

    public function testWmaContentInItsAsfContainerIsAccepted(): void
    {
        $validator = new FileValidator('audio/*(.mp3,.wma),video/*(.mp4)');
        // The detected type is the container, the type to store is the audio format of the extension
        $this->assertEquals(new ValidatedContentType('video/x-ms-asf', 'audio/x-ms-wma'), $validator->validateHead(self::ASF, 'song.wma', 'video/x-ms-asf'));
        $this->assertEquals(new ValidatedContentType('video/x-ms-asf', 'audio/x-ms-wma'), $validator->validateHead(self::ASF, 'song.wma', null));
        $this->assertEquals(new ValidatedContentType('video/x-ms-asf', 'audio/x-ms-wma'), $validator->validateHead(self::ASF, 'song.wma', 'audio/x-ms-wma'));
    }

    public function testWmvWithoutTypeIsAVideo(): void
    {
        $this->expectNotToPerformAssertions();

        (new FileValidator('audio/*(.mp3,.wma),video/*(.mp4,.wmv)'))->validateFile('clip.wmv', null);
    }

    public function testContentOfAnotherFamilyIsRefused(): void
    {
        $this->expectException(ContentTypeMismatchException::class);
        $this->expectExceptionMessage('Declared type "image/jpeg", content is "text/html"');

        // Whatever the allowed types
        (new FileValidator(''))->validateHead('<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'foo.jpg', 'image/jpeg');
    }

    public function testContentOfANotAllowedTypeIsRefused(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "image/svg+xml" with extension "jpg" is not allowed');

        (new FileValidator('image/jpeg(.jpg),image/png(.png)'))->validateHead('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'foo.jpg', 'image/jpeg');
    }

    public function testForbiddenContentIsRefused(): void
    {
        $this->expectException(ForbiddenContentException::class);

        (new FileValidator(''))->validateHead("#EXTM3U\n#EXTINF:10,\nfile:///etc/passwd\n", 'foo.mp4', 'video/mp4');
    }

    public function testValidFileContent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fvt');
        file_put_contents($path, self::PNG);
        try {
            $this->assertEquals(new ValidatedContentType('image/png', 'image/png'), (new FileValidator(self::ALLOWED_TYPES))->validateFileContent($path, 'foo.png', null));
        } finally {
            unlink($path);
        }
    }

    public function testArrayConfiguration(): void
    {
        $validator = new FileValidator(['image/*' => ['.JPG', 'png']]);
        $validator->validateFile('foo.jpg', 'image/jpeg');
        $validator->validateFile('foo.png', 'image/png');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "text/html" with extension "jpg" is not allowed. Allowed types are image/* (.jpg, .png)');
        $validator->validateFile('foo.jpg', 'text/html');
    }

    public function testMisplacedExtensionIsNamedInTheRefusal(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "application/postscript" with extension "ai" is not allowed. The configuration lists ".ai" under image/* only, which none of its types (application/illustrator, application/postscript, application/vnd.adobe.illustrator) matches: list it under application/illustrator or application/*. Allowed types are image/* (.jpg, .ai)');

        (new FileValidator('image/*(.jpg,.ai)'))->validateFile('logo.ai', 'application/postscript');
    }

    public function testExtensionUnknownToTheMimeDatabaseIsNamedInTheRefusal(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "application/x-indesign" with extension "indd" is not allowed. The configuration lists ".indd" under image/* only, which does not match the type of this file (application/x-indesign). Allowed types are image/* (.jpg, .indd)');

        (new FileValidator('image/*(.jpg,.indd)'))->validateFile('file1.indd', 'application/x-indesign');
    }

    public function testRefusalOfAWellPlacedExtensionHasNoDiagnostic(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "text/html" with extension "jpg" is not allowed. Allowed types are image/* (.jpg)');

        (new FileValidator('image/*(.jpg)'))->validateFile('foo.jpg', 'text/html');
    }

    public function testExtensionAlsoListedUnderItsOwnTypeHasNoDiagnostic(): void
    {
        try {
            (new FileValidator('image/*(.jpg,.ai),application/*(.ai)'))->validateFile('foo.ai', 'text/html');
            $this->fail('The file should be refused');
        } catch (BadRequestHttpException $e) {
            $this->assertStringNotContainsString('The configuration lists', $e->getMessage());
        }
    }

    public function testMisplacedExtensionIsLoggedOnce(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('".ai" is listed under image/*, which matches none of its types (application/illustrator, application/postscript, application/vnd.adobe.illustrator)'),
                $this->callback(static fn (array $context): bool => ['image/*'] === ($context['misplaced']['ai']['masks'] ?? null)),
            );

        $validator = new FileValidator('image/*(.jpg,.ai)', $logger);
        $validator->validateFile('foo.jpg', 'image/jpeg');
        $validator->validateFile('bar.jpg', 'image/jpeg');
    }

    #[DataProvider('getConfigurationsWithoutMisplacedExtension')]
    public function testNoWarningWithoutMisplacedExtension(string $allowedTypes): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        try {
            (new FileValidator($allowedTypes, $logger))->validateFile('foo.jpg', 'image/jpeg');
        } catch (BadRequestHttpException) {
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function getConfigurationsWithoutMisplacedExtension(): array
    {
        return [
            'default test configuration' => [self::ALLOWED_TYPES],
            'octet-stream lists any extension' => ['application/octet-stream(.ai)'],
            'extension unknown to the MIME database' => ['image/*(.jpg,.prproj,.indd)'],
            'type libmagic gives to some files of the family' => ['application/*(.epsf,.ogg)'],
        ];
    }
}
