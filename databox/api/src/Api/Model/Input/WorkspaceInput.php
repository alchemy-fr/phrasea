<?php

declare(strict_types=1);

namespace App\Api\Model\Input;

use Symfony\Component\Validator\Constraints as Assert;

class WorkspaceInput extends AbstractOwnerIdInput
{
    public ?string $name = null;
    public ?string $slug = null;

    #[Assert\Range(min: 0, max: 365 * 2)]
    public int|string|null $trashRetentionDelay = null;

    public ?bool $public = null;
    public ?array $enabledLocales = null;
    public ?array $localeFallbacks = null;
    public ?int $assetDefaultStatus = null;
    public ?bool $fileAnalysisRequired = null;
    public ?array $translations = null;

    /**
     * Terms & Conditions text. An empty string clears the terms
     * (a new empty version is recorded to keep signature history).
     * A PDF can be provided instead through POST /workspaces/{id}/terms
     * (multipart) and takes precedence over the text.
     */
    public ?string $terms = null;

    /**
     * Terms & Conditions text translations, indexed by locale
     * (e.g. {"fr": "...", "de": "..."}). Null = untouched.
     */
    public ?array $termsTranslations = null;

    public ?bool $attachTermsToExports = null;
}
