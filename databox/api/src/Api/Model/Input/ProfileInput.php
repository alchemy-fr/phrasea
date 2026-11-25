<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class ProfileInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $description = null;
    public ?bool $public = null;

    /**
     * Applied to the readers of the profile while they have not chosen one.
     * Changing it takes the "assign default profiles" instance capability.
     */
    public ?bool $defaultProfile = null;
    public ?array $data = null;
}
