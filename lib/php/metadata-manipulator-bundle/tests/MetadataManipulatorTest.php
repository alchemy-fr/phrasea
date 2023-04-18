<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPUnit\Framework\TestCase;

class MetadataManipulatorTest extends TestCase
{
    private ?MetadataManipulator $service = null;

    /**
     * @covers MetadataManipulator::getKnownTagGroups
     */
//    public function testGetKnownTagGroups(): void
//    {
//        self::assertIsArray($this->service->getKnownTagGroups());
//    }

    /**
     * @covers MetadataManipulator::createTagGroup
     */
    public function testGroupName(): void
    {
        $o = $this->service->createTagGroup('IFD0:Artist');
        $this->assertEquals("PHPExiftool\Driver\TagGroup\IFD0\Artist", get_class($o));
    }

    /**
     * @covers MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        $this->service = new MetadataManipulator(sys_get_temp_dir());
    }
}
