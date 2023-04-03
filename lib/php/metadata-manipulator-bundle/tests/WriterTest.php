<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\Exception\UnknownTagGroupNameException;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Exception\TagUnknown;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    const TEST_IMAGE_FILE = __DIR__.'/fixtures/image.jpg';
    const TEST_IMAGE_WRITABLE_FILE = __DIR__.'/fixtures/image_w.jpg';

    private ?MetadataManipulator $service = null;

    /**
     * @covers MetadataManipulator::createMetadata
     */
    public function testWrite(): void
    {
        $file = new \SplFileObject(self::TEST_IMAGE_WRITABLE_FILE);
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
     * @covers MetadataManipulator::createMetadata
     */
    public function testWriteUnknown(): void
    {
        $this->expectException(TagUnknown::class);

        $this->service->createMetadata('unknownTagGroup')->setValue('John Doe');
    }

    public static function setUpBeforeClass(): void
    {
        copy(self::TEST_IMAGE_FILE, self::TEST_IMAGE_WRITABLE_FILE);
    }

    public static function tearDownAfterClass(): void
    {
        unlink(self::TEST_IMAGE_WRITABLE_FILE);
    }

    /**
     * @covers MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        $this->service = new MetadataManipulator(['classes_directory' => '/tmp']);
    }
}
