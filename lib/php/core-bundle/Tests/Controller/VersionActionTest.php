<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Controller;

use Alchemy\CoreBundle\Controller\VersionAction;
use PHPUnit\Framework\TestCase;

class VersionActionTest extends TestCase
{
    public function testTheVersionAndTheCommitAreServed(): void
    {
        $response = (new VersionAction('5.0.0', '34886e8a1b2c'))();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['version' => '5.0.0', 'commit' => '34886e8a1b2c'], json_decode((string) $response->getContent(), true));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testAnEmptyCommitIsNull(): void
    {
        $response = (new VersionAction('dev', ''))();

        $this->assertSame(['version' => 'dev', 'commit' => null], json_decode((string) $response->getContent(), true));
    }
}
