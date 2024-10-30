<?php

declare(strict_types=1);

namespace App\Entity\Core;

interface WorkspaceItemPrivacyInterface
{
    // Completely secret, only owner or granted users can view the item
    public const int SECRET = 0;

    // Item is listed for users allowed in the workspace but content is not accessible
    public const int PRIVATE_IN_WORKSPACE = 1;

    // Open to users allowed in the workspace
    public const int PUBLIC_IN_WORKSPACE = 2;

    // Item is listed to every user, but content is not accessible
    public const int PRIVATE = 3;

    // Public to every authenticated users
    public const int PUBLIC_FOR_USERS = 4;

    // Public to everyone
    public const int PUBLIC = 5;

    public const KEYS = [
        WorkspaceItemPrivacyInterface::SECRET => 'secret',
        WorkspaceItemPrivacyInterface::PRIVATE_IN_WORKSPACE => 'private_in_workspace',
        WorkspaceItemPrivacyInterface::PUBLIC_IN_WORKSPACE => 'public_in_workspace',
        WorkspaceItemPrivacyInterface::PRIVATE => 'private',
        WorkspaceItemPrivacyInterface::PUBLIC_FOR_USERS => 'public_for_users',
        WorkspaceItemPrivacyInterface::PUBLIC => 'public',
    ];
}
