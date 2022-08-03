<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\Exception\UnknownTagGroupName;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Reader;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    private ?MetadataManipulator $service = null;
    private ?Reader $reader = null;

    public static function setUpBeforeClass(): void
    {
        // _w will be used for write tests
        copy(__DIR__.'/fixtures/Metadata_test_file.jpg', __DIR__.'/fixtures/Metadata_test_file_w.jpg');
    }

    public static function tearDownAfterClass(): void
    {
        unlink(__DIR__.'/fixtures/Metadata_test_file_w.jpg');
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
     * @covers \MetadataManipulator::createMetadata
     */
    public function testWrite(): void
    {
        $file = new \SplFileObject(__DIR__.'/fixtures/Metadata_test_file_w.jpg');
        $bag = new MetadataBag();

        $artist = $this->service->createMetadata('IFD0:Artist')->setValue('John Doe');
        $bag->add($artist);

        $keywords = $this->service->createMetadata('IPTC:Keywords')->setValue(['John Lennon', 'Paul McCartney', 'George Harrison', 'Ringo Starr']);
        $bag->add($keywords);

        $this->service->setMetadata($file, $bag);

        $meta = $this->service->getAllMetadata($file);

        $artist = $meta->get('IFD0:Artist');
        $this->assertSame('John Doe', $artist->getValue()->asString());

        $keywords = $meta->get('IPTC:Keywords');
        $this->assertSame('John Lennon ; Paul McCartney ; George Harrison ; Ringo Starr', $keywords->getValue()->asString());
    }

    /**
     * @covers \MetadataManipulator::createMetadata
     */
    public function testWriteUnknow(): void
    {
        $this->expectException(UnknownTagGroupName::class);

        $this->service->createMetadata('unknownTagGroup')->setValue('John Doe');
    }
}
