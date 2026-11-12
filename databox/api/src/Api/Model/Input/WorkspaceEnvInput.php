<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;

class WorkspaceEnvInput
{
    /**
     * Null for an instance variable (administrators only). Set on creation only.
     */
    public ?Workspace $workspace = null;

    /**
     * Set on creation only: renaming a variable would break the references to it.
     */
    public ?string $name = null;

    /**
     * Required on creation, may be empty. Unchanged when null.
     */
    public ?string $value = null;
}
