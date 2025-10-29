<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class WorkspaceInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $fileAnalyzers = null;

    #[Assert\Range(min: 0, max: 365 * 2)]
    public int|string|null $trashRetentionDelay = null;

    public ?bool $public = null;
    public ?array $enabledLocales = null;
    public ?array $localeFallbacks = null;
    public ?array $translations = null;
}
