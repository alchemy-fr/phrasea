<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPUnit\Framework\TestCase;

class MetadataManipulatorTest extends TestCase
{
    private ?MetadataManipulator $service = null;

    /**
     * @covers \MetadataManipulator::getKnownTagGroups
     */
    public function testGetKnownTagGroups(): void
    {
        $tagGroups = $this->service->getKnownTagGroups();
        $this->assertContains('IFD0:Artist', $tagGroups);
        $this->assertContains('IPTC:Keywords', $tagGroups);
    }

    /**
     * @covers \MetadataManipulator::createTagGroup
     */
    public function testGroupName(): void
    {
        $o = $this->service->createTagGroup('IFD0:Artist');
        $this->assertEquals(\PHPExiftool\Driver\TagGroup\IFD0\Artist::class, $o::class);
    }

    /**
     * @covers \MetadataManipulator::getReader
     */
    protected function setup(): void
    {
        $this->service = new MetadataManipulator(sys_get_temp_dir());
    }
}
