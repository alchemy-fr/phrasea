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
 * - root: Owner of the workspace so can actually perform everything
 * - alice
 * - bob
 * - carol
 */
class PermissionsTestCase
{
    public function __construct(
        public string $username,
        /**
         * Workspace permissions.
         *
         * @var array
         */
        public array $root = [],
        public array $a = [],
        public array $b = [],
        public array $inAAlice = [],
        public array $inABob = [],
        public array $inBAlice = [],
        public array $inBBob = [],
        /**
         * User ($username) can:
         */
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
