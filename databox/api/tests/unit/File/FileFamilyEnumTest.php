<?php

declare(strict_types=1);

namespace App\Tests\Unit\File;

use App\Entity\Core\File;
use App\Entity\Core\FileFamilyEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileFamilyEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, FileFamilyEnum}>
     */
    public static function mimeTypeProvider(): iterable
    {
        yield 'jpeg' => ['image/jpeg', FileFamilyEnum::Image];
        yield 'svg' => ['image/svg+xml', FileFamilyEnum::Image];
        yield 'photoshop' => ['application/x-photoshop', FileFamilyEnum::Image];
        yield 'mp3' => ['audio/mpeg', FileFamilyEnum::Audio];
        yield 'wav' => ['audio/x-wav', FileFamilyEnum::Audio];
        yield 'mp4' => ['video/mp4', FileFamilyEnum::Video];
        yield 'mxf' => ['application/mxf', FileFamilyEnum::Video];
        yield 'pdf' => ['application/pdf', FileFamilyEnum::Document];
        yield 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', FileFamilyEnum::Document];
        yield 'plain text' => ['text/plain', FileFamilyEnum::Document];
        yield 'with charset' => ['text/csv; charset=utf-8', FileFamilyEnum::Document];
        yield 'uppercase' => ['IMAGE/PNG', FileFamilyEnum::Image];
        yield 'zip' => ['application/zip', FileFamilyEnum::Other];
        yield 'octet-stream' => ['application/octet-stream', FileFamilyEnum::Other];
        yield 'empty' => ['', FileFamilyEnum::Other];
        yield 'null' => [null, FileFamilyEnum::Other];
    }

    #[DataProvider('mimeTypeProvider')]
    public function testFromMimeType(?string $mimeType, FileFamilyEnum $expected): void
    {
        $this->assertSame($expected, FileFamilyEnum::fromMimeType($mimeType));
    }

    public function testFileFamily(): void
    {
        $file = new File();
        $this->assertSame(FileFamilyEnum::Other, $file->getFamily());

        $file->setType('audio/ogg');
        $this->assertSame(FileFamilyEnum::Audio, $file->getFamily());
    }

    public function testValues(): void
    {
        $this->assertSame(['image', 'audio', 'video', 'document', 'other'], FileFamilyEnum::values());
    }
}
