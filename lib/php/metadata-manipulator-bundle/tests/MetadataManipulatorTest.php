<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPUnit\Framework\TestCase;

class MetadataManipulatorTest extends TestCase
{
    private ?MetadataManipulator $service = null;

    /**
     * @covers MetadataManipulator::getKnownTagGroups
     */
    public function testGetKnownTagGroups(): void
    {
        self::assertIsArray($this->service->getKnownTagGroups());
    }

    /**
     * @covers MetadataManipulator::getClassnameFromTagGroupName
     */
    public function testGroupName(): void
    {
        $className = $this->service->getClassnameFromTagGroupName('IFD0:Artist');
        $this->assertSame(Artist::class, $className);

        $o = new $className();
        $this->assertInstanceOf(Artist::class, $o);
    }

    /**
     * @covers MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        $this->service = new MetadataManipulator();
    }
}
