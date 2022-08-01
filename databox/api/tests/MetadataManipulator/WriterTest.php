<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\ApiTest\ApiTestCase;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPExiftool\Driver\TagGroupInterface;
use PHPExiftool\Reader;
use PHPExiftool\Driver\Value\Mono;


class WriterTest extends ApiTestCase
{
    private ?MetadataManipulator $service = null;
    private ?Reader $reader = null;

    public static function setUpBeforeClass(): void
    {
        // _w will be used for write tests
        copy(__DIR__.'/../fixtures/files/Metadata_test_file.jpg', __DIR__.'/../fixtures/files/Metadata_test_file_w.jpg');
    }

    public static function tearDownAfterClass(): void
    {
        unlink(__DIR__.'/../fixtures/files/Metadata_test_file_w.jpg');
    }

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
     * @covers MetadataManipulator::setMetadatas
     */
    public function testWrite(): void
    {
        $f = new \SplFileObject(__DIR__.'/../fixtures/files/Metadata_test_file_w.jpg');
        $bag = new MetadataBag();

        $className = $this->service->getClassnameFromTagGroupName('IFD0:Artist');
        /** @var TagGroupInterface $o */
        $o = new $className;
        $bag->add(new Metadata($o, new Mono('John Doe')));

        $this->service->setMetadatas($f, $bag);

        $metas = $this->service->getMetadatas($f);

        $m = $metas->get('IFD0:Artist');
        $this->assertSame("John Doe", $m->getValue()->asString());
    }
}
