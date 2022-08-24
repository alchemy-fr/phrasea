<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPUnit\Framework\TestCase;

class MetadataManipulatorTest extends TestCase
{
    private ?MetadataManipulator $service = null;

    protected function setup(): void
    {
        $this->service = new MetadataManipulator();
    }

    /**
     * @covers \MetadataManipulator::getKnownTagGroups
     */
    public function testGetKnownTagGroups(): void
    {
        self::assertIsArray($this->service->getKnownTagGroups());
    }

    /**
     * @covers \MetadataManipulator::getClassnameFromTagGroupName
     */
    public function testGroupName(): void
    {
        $className = $this->service->getClassnameFromTagGroupName('IFD0:Artist');
        $this->assertSame('PHPExiftool\\Driver\\TagGroup\\IFD0\\Artist', $className);

        $o = new $className();
        $this->assertInstanceOf(Artist::class, $o);
    }
}
