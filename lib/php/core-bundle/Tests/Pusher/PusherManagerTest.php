<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Pusher;

use Alchemy\CoreBundle\Pusher\PusherFactory;
use Alchemy\CoreBundle\Pusher\PusherManager;
use PHPUnit\Framework\TestCase;

class PusherManagerTest extends TestCase
{
    public function testOnlyAPusherWithAHostIsConfigured(): void
    {
        $this->assertFalse(PusherManager::isConfigured(PusherFactory::create('', 'key', 'secret', 'app')));
        $this->assertTrue(PusherManager::isConfigured(PusherFactory::create('sockudo', 'key', 'secret', 'app')));
    }
}
