<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Upload\FileValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileValidatorTest extends TestCase
{
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

    public function testArrayConfiguration(): void
    {
        $validator = new FileValidator(['image/*' => ['.JPG', 'png']]);
        $validator->validateFile('foo.jpg', 'image/jpeg');
        $validator->validateFile('foo.png', 'image/png');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type "text/html" with extension "jpg" is not allowed. Allowed types are image/* (.jpg, .png)');
        $validator->validateFile('foo.jpg', 'text/html');
    }
}
