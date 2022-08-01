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

    public static function setUpBeforeClass(): void
    {
        // _r will be used for read tests
        copy(__DIR__.'/../fixtures/files/Metadata_test_file.jpg', __DIR__.'/../fixtures/files/Metadata_test_file_r.jpg');
    }

    public static function tearDownAfterClass(): void
    {
        unlink(__DIR__.'/../fixtures/files/Metadata_test_file_r.jpg');
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
     * @covers MetadataManipulator::getMetadatas
     */
    public function testRead(): void
    {
        $metas = $this->service->getMetadatas(new \SplFileObject(__DIR__.'/../fixtures/files/Metadata_test_file_r.jpg'));
        self::assertInstanceOf(MetadataBag::class, $metas);

        $meta = $metas->get('IFD0:Artist');
        $tagGroup = $meta->getTagGroup();
        $this->assertInstanceOf(Artist::class, $tagGroup);
        $this->assertSame("Carl Seibert (Exif)", $meta->getValue()->asString());
    }
}
