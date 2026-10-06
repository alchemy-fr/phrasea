<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use App\Model\SavedSearchPrivacyEnum;
use Symfony\Component\Validator\Constraints as Assert;

class SavedSearchInput extends AbstractOwnerIdInput
{
    public ?string $name = null;

    #[Assert\Choice(callback: [SavedSearchPrivacyEnum::class, 'values'])]
    public ?int $privacy = null;

    public ?array $data = null;
}
