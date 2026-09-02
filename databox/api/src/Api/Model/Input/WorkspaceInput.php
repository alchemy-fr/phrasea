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
     */
    public ?string $terms = null;

    /**
     * Terms & Conditions provided directly as a PDF
     * ("data:application/pdf;base64," data URI). Takes precedence over the text.
     * An empty string removes the PDF (text terms apply again, if any).
     */
    public ?string $termsPdf = null;

    public ?bool $attachTermsToExports = null;

    /**
     * Workspace logo: a URL or a base64-encoded image data URI.
     * An empty string removes the logo.
     */
    #[Assert\AtLeastOneOf([
        new Assert\Url(requireTld: true),
        new Assert\Regex('#^data:image/(png|jpg|jpeg|gif|svg\+xml|webp);base64,[a-zA-Z0-9+/=]+$#'),
        new Assert\Blank(),
    ])]
    public ?string $logo = null;
}
