<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Metadata;

use App\Service\Metadata\WritableMetadataPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WritableMetadataPolicyTest extends TestCase
{
    #[DataProvider('provideAllowedTags')]
    public function testDescriptiveTagsAreAllowed(string $tagGroupId): void
    {
        $policy = new WritableMetadataPolicy();

        $this->assertTrue($policy->isWritable($tagGroupId));
        $policy->assertWritable($tagGroupId);
    }

    public static function provideAllowedTags(): iterable
    {
        yield ['IPTC:Keywords'];
        yield ['iptc:City'];
        yield ['EXIF:Artist'];
        yield ['XMP:XMP'];
        yield ['XMP-dc:Title'];
        yield ['xmp-dc:Subject'];
        yield ['XMP-photoshop:Credit'];
        yield ['XMP-xmpMM:DocumentID'];
        yield ['IFD0:Artist'];
        yield ['ExifIFD:ImageUniqueID'];
        yield ['GPS:GPSLatitude'];
        yield ['Photoshop:URL'];
        yield ['QuickTime:Title'];
        yield ['ID3:Title'];
        yield ['PDF:Author'];
        yield ['Keys:Title'];
        yield ['ItemList:Title'];
        yield ['UserData:Title'];
        yield ['Canon:ImageUniqueID'];
    }

    #[DataProvider('provideRefusedTags')]
    public function testOtherTagsAreRefused(string $tagGroupId): void
    {
        $policy = new WritableMetadataPolicy();

        $this->assertFalse($policy->isWritable($tagGroupId));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s"', $tagGroupId));
        $policy->assertWritable($tagGroupId);
    }

    public static function provideRefusedTags(): iterable
    {
        yield ['File:HardLink'];
        yield ['File:SymLink'];
        yield ['File:TestName'];
        yield ['File:Geotag'];
        yield ['file:hardlink'];
        yield ['System:FileName'];
        yield ['system:FileName'];
        yield ['SYSTEM:FileName'];
        yield ['system:Directory'];
        yield ['Composite:GPSPosition'];
        yield ['ExifTool:ExifToolVersion'];
        yield ['Extra:Anything'];
        yield ['All:PreviewImage'];
        yield ['Nikon:SerialNumber'];
        yield ['XMPfoo:Title'];
        yield ['IPTC'];
        yield [''];
    }
}
