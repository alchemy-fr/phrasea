<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests;

use Alchemy\NotifierBundle\NotifierState;
use PHPUnit\Framework\TestCase;

final class NotifierStateTest extends TestCase
{
    public function testDefaultsToEnabled(): void
    {
        self::assertTrue((new NotifierState())->isEnabled());
    }

    public function testCanBeToggledAtRuntime(): void
    {
        $state = new NotifierState(true);

        $state->setEnabled(false);
        self::assertFalse($state->isEnabled());

        $state->setEnabled(true);
        self::assertTrue($state->isEnabled());
    }

    public function testInitialValueIsHonored(): void
    {
        self::assertFalse((new NotifierState(false))->isEnabled());
    }
}
