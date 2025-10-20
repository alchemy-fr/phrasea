<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

class WorkspaceInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $fileAnalyzers = null;
    public ?bool $public = null;
    public ?array $enabledLocales = null;
    public ?array $localeFallbacks = null;
    public ?array $translations = null;
}
