<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Model\SavedSearchPrivacyEnum;
use Symfony\Component\Validator\Constraints as Assert;

class SavedSearchInput extends AbstractOwnerIdInput
{
    #[Assert\NotBlank]
    public ?string $name = null;

    #[Assert\Choice(callback: [SavedSearchPrivacyEnum::class, 'values'])]
    public ?int $privacy = null;

    #[Assert\NotNull]
    public ?array $data = null;
}
