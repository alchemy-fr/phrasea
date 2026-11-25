<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Home\MimeTypeFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MimeTypeFamilyTest extends TestCase
{
    #[DataProvider('getFamilyCases')]
    public function testGetFamily(string $mimeType, string $expectedFamily): void
    {
        $this->assertSame($expectedFamily, MimeTypeFamily::getFamily($mimeType));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function getFamilyCases(): iterable
    {
        yield 'jpeg' => ['image/jpeg', MimeTypeFamily::IMAGE];
        yield 'svg' => ['image/svg+xml', MimeTypeFamily::IMAGE];
        yield 'mp4' => ['video/mp4', MimeTypeFamily::VIDEO];
        yield 'mp3' => ['audio/mpeg', MimeTypeFamily::AUDIO];
        yield 'pdf' => ['application/pdf', MimeTypeFamily::DOCUMENT];
        yield 'text' => ['text/plain', MimeTypeFamily::DOCUMENT];
        yield 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', MimeTypeFamily::DOCUMENT];
        yield 'ogg' => ['application/ogg', MimeTypeFamily::VIDEO];
        yield 'mxf' => ['application/mxf', MimeTypeFamily::VIDEO];
        yield 'photoshop' => ['application/x-photoshop', MimeTypeFamily::IMAGE];
        yield 'postscript' => ['application/postscript', MimeTypeFamily::IMAGE];
        yield 'illustrator' => ['application/illustrator', MimeTypeFamily::IMAGE];
        yield 'with parameters and case' => ['Text/Plain; charset=UTF-8', MimeTypeFamily::DOCUMENT];
        yield 'zip' => ['application/zip', MimeTypeFamily::OTHER];
        yield 'html' => ['text/html', MimeTypeFamily::OTHER];
        yield 'empty' => ['', MimeTypeFamily::OTHER];
    }

    public function testAggregateSortsTheFamiliesByCount(): void
    {
        $this->assertSame([
            MimeTypeFamily::IMAGE => 7,
            MimeTypeFamily::DOCUMENT => 3,
            MimeTypeFamily::VIDEO => 2,
            MimeTypeFamily::OTHER => 1,
        ], MimeTypeFamily::aggregate([
            'application/pdf' => 3,
            'image/jpeg' => 4,
            'application/zip' => 1,
            'image/png' => 3,
            'application/ogg' => 2,
        ]));
    }

    public function testAggregateEmpty(): void
    {
        $this->assertSame([], MimeTypeFamily::aggregate([]));
    }
}
