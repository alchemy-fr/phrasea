<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Target;
use PHPUnit\Framework\TestCase;

class TargetAllowedGroupsTest extends TestCase
{
    public function testStoredGroupsWithGapsAreReadAsList(): void
    {
        $target = new Target();
        // As Doctrine hydrates a row saved with gaps in its keys
        new \ReflectionProperty(Target::class, 'allowedGroups')->setValue($target, [1 => 'group-a', 3 => 'group-b']);

        $this->assertSame(['group-a', 'group-b'], $target->getAllowedGroups());
        $this->assertSame('["group-a","group-b"]', json_encode($target->getAllowedGroups()));
        $this->assertTrue($target->isAllowedFor(['group-b']));
    }

    public function testGroupsAreStoredAsList(): void
    {
        $target = new Target();
        $target->setAllowedGroups([2 => 'group-a', 5 => 'group-b']);

        $this->assertSame(['group-a', 'group-b'], new \ReflectionProperty(Target::class, 'allowedGroups')->getValue($target));
    }

    public function testNoGroupIsStoredAsNull(): void
    {
        $target = new Target();
        $target->setAllowedGroups([]);

        $this->assertNull($target->getAllowedGroups());
        $this->assertTrue($target->isAllowedFor([]));
    }
}
