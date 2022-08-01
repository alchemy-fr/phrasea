<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\ApiTest\ApiTestCase;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;
use PHPExiftool\Driver\TagGroup\IFD0\Artist;
use PHPExiftool\Driver\TagGroupInterface;

class ServiceTest extends ApiTestCase
{
    private ?MetadataManipulator $service = null;

    protected function setup(): void
    {
        try {
            $this->service = static::getContainer()->get('metadata-manipulator');
        }
        catch(\Exception $e) {
            // no-op;
        }
        $this->assertNotNull($this->service);
    }

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
        $this->assertSame('PHPExiftool\\Driver\\TagGroup\\IFD0\\Artist', $className);

        $o = new $className;
        $this->assertInstanceOf(Artist::class, $o);
    }
}
