<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPExiftool\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    private ?MetadataManipulator $service = null;
    private ?Reader $reader = null;

    public static function setUpBeforeClass(): void
    {
        // _r will be used for read tests
        copy(__DIR__.'/fixtures/Metadata_test_file.jpg', __DIR__.'/fixtures/Metadata_test_file_r.jpg');
    }

    public static function tearDownAfterClass(): void
    {
        unlink(__DIR__.'/fixtures/Metadata_test_file_r.jpg');
    }

    /**
     * @covers \MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        $this->service = new MetadataManipulator();
        $this->reader = $this->service->getReader();

        $this->assertNotNull($this->reader);
    }

    /**
     * @covers \MetadataManipulator::getAllMetadata
     */
    public function testRead(): void
    {
        $meta = $this->service->getAllMetadata(new \SplFileObject(__DIR__.'/fixtures/Metadata_test_file_r.jpg'));
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
    public function testGetUnknow(): void
    {
        $meta = $this->service->getAllMetadata(new \SplFileObject(__DIR__.'/fixtures/Metadata_test_file_r.jpg'));
        self::assertInstanceOf(MetadataBag::class, $meta);

        $this->assertNull($meta->get('unknownTagGroup'));
    }
}
