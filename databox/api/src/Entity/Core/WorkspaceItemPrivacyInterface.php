<?php

declare(strict_types=1);

namespace App\Entity\Core;

interface WorkspaceItemPrivacyInterface
{
    // Completely secret, only owner or granted users can view the item
    public const SECRET = 0;

    // Item is listed for users allowed in the workspace but content is not accessible
    public const PRIVATE_IN_WORKSPACE = 1;

    // Open to users allowed in the workspace
    public const PUBLIC_IN_WORKSPACE = 2;

    // Item is listed to every users, but content is not accessible
    public const PRIVATE = 3;

    // Public to every authenticated users
    public const PUBLIC_FOR_USERS = 4;

    // Public to everyone
    public const PUBLIC = 5;

    public const LABELS = [
        WorkspaceItemPrivacyInterface::SECRET => 'Secret',
        WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE => 'Private in workspace',
        WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE => 'Public in workspace',
        WorkspaceItemPrivacyInterface::PRIVATE => 'Private',
        WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS => 'Public for users',
        WorkspaceItemPrivacyInterface::PUBLIC => 'Public',
    ];
}
