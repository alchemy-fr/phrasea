<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class SavedSearchInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?bool $public = null;
}
