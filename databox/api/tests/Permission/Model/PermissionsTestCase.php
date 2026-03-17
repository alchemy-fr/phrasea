<?php

namespace App\Tests\Permission\Model;

/**
 * Main invariant structure:
 *
 * Workspace "Sandbox" (owned by root)
 * |- Asset "Lost-alice" (owned by alice)
 * |- Asset "Lost-bob" (owned by bob)
 * |- Collection "A"
 * |  |- Asset "InA-alice" (owned by alice)
 * |  |- Asset "InA-bob" (owned by bob)
 * |  |- Collection "B"(owned by bob)
 * |  |  |- Asset "InB-alice" (owned by alice)
 * |  |  |- Asset "InB-bob" (owned by bob)
 *
 * Users:
 * - root: Owner of the workspace so should actually be able to do everything
 * - alice
 * - bob
 * - carol
 */
class PermissionsTestCase
{
    public function __construct(
        // Username of user which we are testing permissions
        public string $username,
        // Workspace permissions
        public array $root = [],

        // Collection "A" permissions
        public array $a = [],

        // Collection "B" permissions
        public array $b = [],

        // Asset "InA-alice"
        public array $inAAlice = [],

        // Asset "InA-bob"
        public array $inABob = [],

        // Asset "InB-alice"
        public array $inBAlice = [],

        // Asset "InB-bob"
        public array $inBBob = [],

        // Expectations of what user can do
        public bool $canViewRoot = true,
        public bool $canEditRoot = false,
        public bool $canDeleteRoot = false,
        public bool $canCreateCollectionInRoot = false,
        public bool $canCreateAssetInRoot = false,
        public bool $canViewA = false,
        public bool $canEditA = false,
        public bool $canDeleteA = false,
        public bool $canCreateCollectionUnderA = false,
        public bool $canCreateAssetInA = false,
        public bool $canViewB = false,
        public bool $canEditB = false,
        public bool $canDeleteB = false,
        public bool $canCreateCollectionUnderB = false,
        public bool $canCreateAssetInB = false,
    ) {
    }
}
