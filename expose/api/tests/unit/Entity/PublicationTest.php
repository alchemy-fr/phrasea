<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Publication;
use PHPUnit\Framework\TestCase;

class PublicationTest extends TestCase
{
    public function testAHiddenAncestorHidesItsDescendants(): void
    {
        $root = $this->createPublication();
        $parent = $this->createPublication($root);
        $child = $this->createPublication($parent);
        $this->assertTrue($child->isVisible());

        $root->getConfig()->setEnabled(false);
        $this->assertFalse($child->isVisible());

        $root->getConfig()->setEnabled(true);
        $root->getConfig()->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->assertFalse($child->isVisible());

        $root->getConfig()->setExpiresAt(null);
        $parent->getConfig()->setBeginsAt(new \DateTimeImmutable('+1 hour'));
        $this->assertFalse($child->isVisible());
        $this->assertTrue($root->isVisible());
    }

    public function testVisibleUntilTheEarliestExpirationOfItsAncestors(): void
    {
        $root = $this->createPublication();
        $parent = $this->createPublication($root);
        $child = $this->createPublication($parent);
        $this->assertNull($child->getVisibleUntil());

        $rootExpiration = new \DateTimeImmutable('+2 hours');
        $root->getConfig()->setExpiresAt($rootExpiration);
        $this->assertSame($rootExpiration, $child->getVisibleUntil());

        $childExpiration = new \DateTimeImmutable('+3 hours');
        $child->getConfig()->setExpiresAt($childExpiration);
        $this->assertSame($rootExpiration, $child->getVisibleUntil());

        $parentExpiration = new \DateTimeImmutable('+1 hour');
        $parent->getConfig()->setExpiresAt($parentExpiration);
        $this->assertSame($parentExpiration, $child->getVisibleUntil());
    }

    public function testThePublicationOfAProtectedAncestorIsNotPublic(): void
    {
        $root = $this->createPublication();
        $child = $this->createPublication($root);
        $this->assertTrue($child->isPublic());

        $root->getConfig()->setPassword('s3cr3t');
        $this->assertFalse($child->isPublic());
    }

    private function createPublication(?Publication $parent = null): Publication
    {
        $publication = new Publication();
        $publication->getConfig()->setEnabled(true);
        $parent?->addChild($publication);

        return $publication;
    }
}
