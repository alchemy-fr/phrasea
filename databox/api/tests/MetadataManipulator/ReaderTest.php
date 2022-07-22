<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\ApiTest\ApiTestCase;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPExiftool\Reader;

class ReaderTest extends ApiTestCase
{
    private ?MetadataManipulator $service = null;
    private ?Reader $reader = null;


    /**
     * @covers MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        try {
            $this->service = static::getContainer()->get('metadata-manipulator');
            $this->reader = $this->service->getReader();
        }
        catch(\Exception $e) {
            // no-op
        }
        $this->assertNotNull($this->reader);
    }

    /**
     * @covers MetadataManipulator::getMetadatas
     */
    public function testRead(): void
    {
        $metas = $this->service->getMetadatas(new \SplFileObject(__DIR__.'/../fixtures/files/Metadata_test_file.jpg'));
        self::assertInstanceOf(MetadataBag::class, $metas);

        $m = $metas->get('IFD0:Artist');
        $t = $m->getTag();
        $this->assertInstanceOf(Artist::class, $t);
        $this->assertSame("Carl Seibert (Exif)", $m->getValue()->asString());
    }
}
