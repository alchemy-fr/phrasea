<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;

class WorkspaceSecretInput
{
    /**
     * Null for an instance secret (administrators only). Set on creation only.
     */
    public ?Workspace $workspace = null;

    /**
     * Set on creation only: renaming a secret would break the references to it.
     */
    public ?string $name = null;

    /**
     * Write only: never returned. Replaces the current value when not empty.
     */
    public ?string $value = null;
}
