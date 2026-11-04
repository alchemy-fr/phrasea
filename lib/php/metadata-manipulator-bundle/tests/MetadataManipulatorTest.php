<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(MetadataManipulator::class, 'getKnownTagGroups')]
#[CoversMethod(MetadataManipulator::class, 'createTagGroup')]
#[CoversMethod(MetadataManipulator::class, 'getReader')]
class MetadataManipulatorTest extends TestCase
{
    private ?MetadataManipulator $service = null;

    public function testGetKnownTagGroups(): void
    {
        $tagGroups = $this->service->getKnownTagGroups();
        $this->assertContains('IFD0:Artist', $tagGroups);
        $this->assertContains('IPTC:Keywords', $tagGroups);
    }

    public function testGroupName(): void
    {
        $o = $this->service->createTagGroup('IFD0:Artist');
        $this->assertEquals(\PHPExiftool\Driver\TagGroup\IFD0\Artist::class, $o::class);
    }

    protected function setup(): void
    {
        $this->service = new MetadataManipulator(sys_get_temp_dir());
    }
}
