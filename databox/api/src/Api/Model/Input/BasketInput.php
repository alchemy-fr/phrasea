<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class BasketInput extends AbstractOwnerIdInput
{
    public ?string $title = null;
    public ?string $description = null;
}
