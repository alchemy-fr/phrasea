<?php

declare(strict_types=1);

namespace App\Tests\File;

use App\Entity\Core\File;
use PHPUnit\Framework\TestCase;

/**
 * FileMetadata only ever reflects what was read from the file; everything the application
 * sets goes to the FileOverriddenMetadata attached to the File.
 */
class FileOverriddenMetadataTest extends TestCase
{
    private const array READ = [
        'IPTC' => [
            'Keywords' => ['dog', 'cat'],
            'City' => ['Paris'],
        ],
        'XMP' => [
            'DocumentID' => ['original-duid'],
        ],
    ];

    public function testSetMetadataValueLeavesTheReadMetadataUntouched(): void
    {
        $file = $this->createFile();
        $file->setMetadataValue('XMP:DocumentID', 'new-duid');

        $this->assertSame(self::READ, $file->getReadMetadata());
        $this->assertSame(['XMP' => ['DocumentID' => ['new-duid']]], $file->getOverriddenMetadata());
        $this->assertSame(['XMP:DocumentID' => ['new-duid']], $file->getOverriddenMetadataValues());
    }

    public function testOverrideWinsOnRead(): void
    {
        $file = $this->createFile();
        $file->setMetadataValue('XMP:DocumentID', 'new-duid');

        $this->assertSame(['new-duid'], $file->getMetadataNameValues('XMP:DocumentID'));
        $this->assertSame(['new-duid'], $file->getMetadata('XMP:DocumentID'));
        $this->assertSame(['Paris'], $file->getMetadataNameValues('IPTC:City'));
        $this->assertNull($file->getMetadataNameValues('IPTC:Unknown'));
    }

    public function testResolvedMetadataMergesOverridesOverReadValues(): void
    {
        $file = $this->createFile();
        $file->setMetadataValue('XMP:DocumentID', 'new-duid');
        $file->setMetadataValue('XMP:Rating', '5');

        $this->assertSame([
            'IPTC' => [
                'Keywords' => ['dog', 'cat'],
                'City' => ['Paris'],
            ],
            'XMP' => [
                'DocumentID' => ['new-duid'],
                'Rating' => ['5'],
            ],
        ], $file->getMetadata());

        $this->assertSame([
            'IPTC:Keywords' => ['dog', 'cat'],
            'IPTC:City' => ['Paris'],
            'XMP:DocumentID' => ['new-duid'],
            'XMP:Rating' => ['5'],
        ], $file->getMetadataValues());
    }

    public function testAppendSeedsTheOverrideFromTheReadValues(): void
    {
        $file = $this->createFile();

        $file->setMetadataValue('IPTC:Keywords', 'bird', append: true);
        $file->setMetadataValue('IPTC:Keywords', 'fish', append: true);

        $this->assertSame(['dog', 'cat'], $file->getReadMetadata()['IPTC']['Keywords']);
        $this->assertSame(['dog', 'cat', 'bird', 'fish'], $file->getMetadataNameValues('IPTC:Keywords'));
    }

    public function testAppendOnATagAbsentFromTheFileStartsEmpty(): void
    {
        $file = $this->createFile();

        $file->setMetadataValue('XMP:Subject', 'new', append: true);

        $this->assertSame(['new'], $file->getMetadataNameValues('XMP:Subject'));
    }

    public function testRemoveMetadataValueOnlyDropsTheOverride(): void
    {
        $file = $this->createFile();
        $file->setMetadataValue('IPTC:City', 'Lyon');

        $file->removeMetadataValue('IPTC:City');

        $this->assertSame([], $file->getOverriddenMetadata());
        $this->assertSame(['Paris'], $file->getMetadataNameValues('IPTC:City'), 'the value read from the file comes back');
    }

    public function testMetadataHasChangedFollowsTheOverrides(): void
    {
        $file = $this->createFile();
        $this->assertFalse($file->metadataHasChanged());

        $file->setMetadataValue('XMP:DocumentID', 'new-duid');
        $this->assertTrue($file->metadataHasChanged());
    }

    public function testSetMetadataValueRejectsANonNamespacedTag(): void
    {
        $file = new File();

        $this->expectException(\InvalidArgumentException::class);
        $file->setMetadataValue('Keywords', 'dog');
    }

    public function testFileWithoutAnyMetadata(): void
    {
        $file = new File();

        $this->assertNull($file->getMetadata());
        $this->assertNull($file->getReadMetadata());
        $this->assertSame([], $file->getOverriddenMetadata());
        $this->assertSame([], $file->getOverriddenMetadataValues());
        $this->assertSame([], $file->getMetadataValues());
        $this->assertFalse($file->metadataHasChanged());
    }

    public function testOverridesSurviveAFileWithoutReadMetadata(): void
    {
        $file = new File();
        $file->setMetadataValue('XMP:DocumentID', 'new-duid');

        $this->assertNull($file->getReadMetadata());
        $this->assertSame(['XMP' => ['DocumentID' => ['new-duid']]], $file->getMetadata());
    }

    private function createFile(): File
    {
        $file = new File();
        $file->setMetadata(self::READ);

        return $file;
    }
}
