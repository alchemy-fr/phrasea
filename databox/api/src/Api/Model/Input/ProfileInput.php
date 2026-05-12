<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class ProfileInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $description = null;
    public ?bool $public = null;
    public ?array $data = null;
}
