<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class AttributeListInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?string $description = null;
    public ?bool $public = null;
}
