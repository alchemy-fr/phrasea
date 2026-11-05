<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\InstancePermission;
use App\Security\Voter\DataboxExtraPermissionInterface;
use PHPUnit\Framework\TestCase;

class InstancePermissionTest extends TestCase
{
    public function testTheMetadataValuesAreDistinct(): void
    {
        $values = new \ReflectionClass(DataboxExtraPermissionInterface::class)->getConstants();

        $this->assertSame(array_values($values), array_values(array_unique($values)));
        foreach (InstancePermission::all() as $permission) {
            $this->assertContains(InstancePermission::getMetadata($permission), $values);
        }
    }
}
