<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;

class TagInput extends AbstractOwnerIdInput
{
    public ?Workspace $workspace = null;

    public ?string $name = null;

    public ?array $translations = null;

    public ?string $color = null;

    public ?string $key = null;
}
