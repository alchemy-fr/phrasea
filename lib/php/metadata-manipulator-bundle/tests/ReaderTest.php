<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    public const TEST_IMAGE_FILE = __DIR__.'/fixtures/image.jpg';

    private ?MetadataManipulator $service = null;

    /**
     * @covers \MetadataManipulator::getAllMetadata
     */
    public function testRead(): void
    {
        $meta = $this->service->getAllMetadata(new \SplFileObject(self::TEST_IMAGE_FILE));
        self::assertInstanceOf(MetadataBag::class, $meta);

        $artist = $meta->get('IFD0:Artist');
        $this->assertInstanceOf(Metadata::class, $artist);

        $tagGroup = $artist->getTagGroup();
        $this->assertInstanceOf(Artist::class, $tagGroup);

        $this->assertSame('Carl Seibert (Exif)', $artist->getValue()->asString());
    }

    /**
     * @covers \MetadataManipulator::getAllMetadata
     */
    public function testGetUnknown(): void
    {
        $meta = $this->service->getAllMetadata(new \SplFileObject(self::TEST_IMAGE_FILE));
        self::assertInstanceOf(MetadataBag::class, $meta);

        $this->assertNull($meta->get('unknownTagGroup'));
    }

    protected function setup(): void
    {
        $this->service = new MetadataManipulator(sys_get_temp_dir());
    }
}
