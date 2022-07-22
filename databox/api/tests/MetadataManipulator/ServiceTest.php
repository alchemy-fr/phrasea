<?php

declare(strict_types=1);

namespace App\Tests\MetadataManipulator;

use Alchemy\ApiTest\ApiTestCase;
use Alchemy\MetadataManipulatorBundle\MetadataManipulator;

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
}
