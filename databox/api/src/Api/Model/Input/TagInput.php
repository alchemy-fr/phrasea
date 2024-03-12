<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Entity\Core\Workspace;
use Symfony\Component\Validator\Constraints as Assert;

class TagInput extends AbstractOwnerIdInput
{
    #[Assert\NotNull]
    public ?Workspace $workspace = null;

    #[Assert\NotBlank]
    public ?string $name = null;

    public ?array $translations = null;

    public ?string $color = null;

    public ?string $key = null;
}
