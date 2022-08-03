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
        $this->service = static::getContainer()->get('metadata-manipulator');
        $this->assertNotNull($this->service);
    }
}
