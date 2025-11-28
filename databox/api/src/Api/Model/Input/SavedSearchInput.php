<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SavedSearchInput extends AbstractOwnerIdInput
{
    #[Assert\NotBlank]
    public ?string $title = null;
    public ?bool $public = null;

    #[Assert\NotNull]
    public ?array $data = null;
}
