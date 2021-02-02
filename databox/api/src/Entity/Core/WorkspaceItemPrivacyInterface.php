<?php

declare(strict_types=1);

namespace App\Entity\Core;

interface WorkspaceItemPrivacyInterface
{
    public const SECRET = 0;
    public const PRIVATE = 1;
    public const PUBLIC_IN_WORKSPACE = 2;
    public const PUBLIC = 3;
}
