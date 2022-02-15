<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class WorkspaceInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $slug = null;
}
